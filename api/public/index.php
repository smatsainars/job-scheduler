// api/public/index.php
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
use App\Auth\GoogleOAuthHandler;
use App\Middleware\AuthMiddleware;

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', $_ENV['APP_DEBUG'] === 'true' ? '1' : '0');

// Set headers for CORS and JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ($_ENV['CORS_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Initialize authentication components
$authHandler = new GoogleOAuthHandler($entityManager, $_ENV['JWT_SECRET']);
$authMiddleware = new AuthMiddleware($authHandler);

// Initialize FastRoute dispatcher
$dispatcher = FastRoute\simpleDispatcher(function(RouteCollector $r) {
    // Health check endpoint
    $r->get('/health', 'health');
    
    // Test endpoint for development
    $r->get('/test', 'test');
    
    // Authentication endpoints
    $r->post('/auth/google', 'auth_google');
    $r->get('/auth/google/callback', 'auth_google_callback');
    $r->post('/auth/logout', 'auth_logout');
    $r->get('/auth/me', 'auth_me');
    
    // GraphQL endpoint (requires authentication)
    $r->post('/graphql', 'graphql');
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
                    'POST /auth/google' => 'Start Google OAuth flow',
                    'GET /auth/google/callback' => 'Google OAuth callback',
                    'POST /auth/logout' => 'Logout user',
                    'GET /auth/me' => 'Get current user info',
                    'POST /graphql' => 'GraphQL API (requires authentication)'
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
            
            // Check if authentication is required
            if ($authMiddleware->requiresAuth($uri, $httpMethod)) {
                $authResult = $authMiddleware->authenticate();
                
                if (!$authResult) {
                    $authMiddleware->sendUnauthorizedResponse();
                    return;
                }
                
                $currentUser = $authResult['user'];
                $currentToken = $authResult['token'];
            } else {
                $currentUser = null;
                $currentToken = null;
            }
            
            // Handle different endpoints
            switch ($handler) {
                case 'health':
                    handleHealthCheck();
                    break;
                    
                case 'test':
                    handleTest($entityManager);
                    break;
                    
                case 'auth_google':
                    handleGoogleAuth($authHandler);
                    break;
                    
                case 'auth_google_callback':
                    handleGoogleCallback($authHandler);
                    break;
                    
                case 'auth_logout':
                    handleLogout($currentUser);
                    break;
                    
                case 'auth_me':
                    handleGetCurrentUser($currentUser);
                    break;
                    
                case 'graphql':
                    handleGraphQL($entityManager, $currentUser);
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
        $response['trace'] = $e->getTraceAsString();
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
        'debug_mode' => $_ENV['APP_DEBUG'] === 'true',
        'authentication' => 'Google OAuth enabled'
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
            'google_oauth_configured' => !empty($_ENV['GOOGLE_CLIENT_ID']),
            'packages_loaded' => [
                'doctrine' => class_exists('Doctrine\ORM\EntityManager'),
                'graphql' => class_exists('GraphQL\GraphQL'),
                'jwt' => class_exists('Firebase\JWT\JWT'),
                'carbon' => class_exists('Carbon\Carbon'),
                'fastroute' => class_exists('FastRoute\Dispatcher'),
                'google' => class_exists('Google\Client'),
            ]
        ]);
    } catch (Exception $e) {
        throw new Exception('Database test failed: ' . $e->getMessage());
    }
}

/**
 * Start Google OAuth flow
 */
function handleGoogleAuth(GoogleOAuthHandler $authHandler): void
{
    try {
        $authUrl = $authHandler->getAuthUrl();
        
        echo json_encode([
            'success' => true,
            'auth_url' => $authUrl,
            'message' => 'Redirect user to this URL to start Google OAuth flow'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to generate auth URL: ' . $e->getMessage()
        ]);
    }
}

/**
 * Handle Google OAuth callback
 */
function handleGoogleCallback(GoogleOAuthHandler $authHandler): void
{
    $authCode = $_GET['code'] ?? null;
    
    if (!$authCode) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Authorization code not provided'
        ]);
        return;
    }
    
    $result = $authHandler->handleCallback($authCode);
    
    if (!$result['success']) {
        http_response_code(401);
    }
    
    echo json_encode($result);
}

/**
 * Logout endpoint
 */
function handleLogout($currentUser): void
{
    // In a stateless JWT system, logout is handled client-side by removing the token
    // But we can update the user's last activity
    if ($currentUser) {
        // Could implement token blacklisting here if needed
        echo json_encode([
            'success' => true,
            'message' => 'Logged out successfully. Please remove the token from client storage.'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Already logged out'
        ]);
    }
}

/**
 * Get current user info
 */
function handleGetCurrentUser($currentUser): void
{
    if ($currentUser) {
        echo json_encode([
            'success' => true,
            'user' => $currentUser->jsonSerialize()
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Not authenticated'
        ]);
    }
}

/**
 * GraphQL endpoint
 */
function handleGraphQL($entityManager, $currentUser): void
{
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

        // Add authentication context
        $context = [
            'user' => $currentUser,
            'authenticated' => $currentUser !== null
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