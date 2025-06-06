<?php

declare(strict_types=1);

// Get EntityManager from bootstrap
try {
    $entityManager = require_once __DIR__ . '/../bootstrap.php';
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Bootstrap failed',
        'message' => $e->getMessage()
    ]);
    exit;
}

use FastRoute\RouteCollector;
use Carbon\Carbon;
use App\GraphQL\GraphQLHandler;

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', $_ENV['APP_DEBUG'] === 'true' ? '1' : '0');

// Set headers for CORS and JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ($_ENV['CORS_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Initialize FastRoute dispatcher
$dispatcher = FastRoute\simpleDispatcher(function(RouteCollector $r) {
    // Health check endpoint
    $r->get('/health', 'health');
    
    // Test endpoint for development
    $r->get('/test', 'test');
    
    // GraphQL endpoint
    $r->post('/graphql', 'graphql');
    
    // Authentication endpoints (coming soon)
    $r->post('/auth/google', 'auth_google');
});

// Get route info
$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Strip query string and decode URL
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);

try {
    switch ($routeInfo[0]) {
        case FastRoute\Dispatcher::NOT_FOUND:
            http_response_code(404);
            echo json_encode([
                'error' => 'Not Found',
                'message' => 'The requested endpoint does not exist',
                'available_endpoints' => [
                    'GET /health' => 'Health check',
                    'GET /test' => 'Test database connection',
                    'POST /graphql' => 'GraphQL API',
                    'POST /auth/google' => 'Google authentication (coming soon)'
                ]
            ]);
            break;
            
        case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
            $allowedMethods = $routeInfo[1];
            http_response_code(405);
            echo json_encode([
                'error' => 'Method Not Allowed',
                'message' => 'Allowed methods: ' . implode(', ', $allowedMethods)
            ]);
            break;
            
        case FastRoute\Dispatcher::FOUND:
            $handler = $routeInfo[1];
            $vars = $routeInfo[2];
            
            // Handle different endpoints
            switch ($handler) {
                case 'health':
                    handleHealthCheck();
                    break;
                    
                case 'test':
                    handleTest($entityManager);
                    break;
                    
                case 'auth_google':
                    handleGoogleAuth($entityManager);
                    break;
                    
                case 'graphql':
                    handleGraphQL($entityManager);
                    break;
                    
                default:
                    http_response_code(500);
                    echo json_encode(['error' => 'Handler not implemented']);
            }
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    $response = ['error' => 'Internal Server Error'];
    
    if ($_ENV['APP_DEBUG'] === 'true') {
        $response['message'] = $e->getMessage();
        $response['file'] = $e->getFile();
        $response['line'] = $e->getLine();
    }
    
    echo json_encode($response);
}

/**
 * Health check endpoint
 */
function handleHealthCheck(): void
{
    echo json_encode([
        'status' => 'OK',
        'message' => 'Job Scheduler API is running!',
        'timestamp' => time(),
        'php_version' => PHP_VERSION,
        'environment' => $_ENV['APP_ENV'] ?? 'unknown',
        'debug_mode' => $_ENV['APP_DEBUG'] === 'true'
    ]);
}

/**
 * Test endpoint for development
 */
function handleTest($entityManager): void
{
    try {
        // Test database connection
        $connection = $entityManager->getConnection();
        $result = $connection->executeQuery("SELECT 1 as test, NOW() as current_time")->fetchAssociative();
        
        echo json_encode([
            'message' => 'API is working perfectly!',
            'database_status' => 'Connected ✅',
            'test_query' => $result,
            'environment_loaded' => !empty($_ENV['DB_NAME']),
            'database_name' => $_ENV['DB_NAME'],
            'packages_loaded' => [
                'doctrine' => class_exists('Doctrine\ORM\EntityManager'),
                'graphql' => class_exists('GraphQL\GraphQL'),
                'jwt' => class_exists('Firebase\JWT\JWT'),
                'carbon' => class_exists('Carbon\Carbon'),
                'fastroute' => class_exists('FastRoute\Dispatcher'),
            ]
        ]);
    } catch (Exception $e) {
        throw new Exception('Database test failed: ' . $e->getMessage());
    }
}

/**
 * GraphQL endpoint
 */
function handleGraphQL($entityManager): void
{
    // Set CORS headers for GraphQL endpoint
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed. Use POST.']);
        return;
    }

    try {
        // Get request body
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            throw new Exception('Invalid JSON in request body');
        }

        $query = $data['query'] ?? null;
        $variables = $data['variables'] ?? null;

        if (!$query) {
            throw new Exception('GraphQL query is required');
        }

        // Create GraphQL handler
        $graphqlHandler = new GraphQLHandler($entityManager);

        // TODO: Add authentication context here
        $context = [
            'user' => null // Will be populated by authentication middleware
        ];

        // Execute GraphQL query
        $result = $graphqlHandler->handle($query, $variables, $context);

        header('Content-Type: application/json');
        echo json_encode($result);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'errors' => [
                [
                    'message' => $e->getMessage()
                ]
            ]
        ]);
    }
}

/**
 * Google authentication endpoint (placeholder)
 */
function handleGoogleAuth($entityManager): void
{
    echo json_encode([
        'message' => 'Google authentication endpoint - coming soon!',
        'note' => 'Will handle Google OAuth login',
        'timestamp' => time()
    ]);
}