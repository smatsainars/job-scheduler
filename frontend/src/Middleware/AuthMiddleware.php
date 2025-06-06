// api/src/Middleware/AuthMiddleware.php
<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\GoogleOAuthHandler;

class AuthMiddleware
{
    private GoogleOAuthHandler $authHandler;

    public function __construct(GoogleOAuthHandler $authHandler)
    {
        $this->authHandler = $authHandler;
    }

    /**
     * Extract and verify JWT token from request headers
     */
    public function authenticate(): ?array
    {
        $authHeader = $this->getAuthorizationHeader();
        
        if (!$authHeader) {
            return null;
        }

        // Extract Bearer token
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            
            $user = $this->authHandler->verifyToken($token);
            
            if ($user) {
                return [
                    'user' => $user,
                    'token' => $token
                ];
            }
        }

        return null;
    }

    /**
     * Check if current request requires authentication
     */
    public function requiresAuth(string $path, string $method): bool
    {
        // Public endpoints that don't require authentication
        $publicEndpoints = [
            'GET:/health',
            'GET:/test',
            'POST:/auth/google',
            'GET:/auth/google/callback',
            'OPTIONS:*' // Allow all OPTIONS requests for CORS
        ];

        $current = strtoupper($method) . ':' . $path;

        foreach ($publicEndpoints as $endpoint) {
            if ($endpoint === $current || 
                (str_ends_with($endpoint, '*') && str_starts_with($current, rtrim($endpoint, '*')))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get Authorization header from request
     */
    private function getAuthorizationHeader(): ?string
    {
        $headers = null;
        
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(
                array_map('ucwords', array_keys($requestHeaders)), 
                array_values($requestHeaders)
            );
            
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }

        return $headers;
    }

    /**
     * Send unauthorized response
     */
    public function sendUnauthorizedResponse(): void
    {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Unauthorized',
            'message' => 'Authentication required. Please login to access this resource.',
            'code' => 401
        ]);
        exit;
    }

    /**
     * Send forbidden response
     */
    public function sendForbiddenResponse(): void
    {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Forbidden',
            'message' => 'You do not have permission to access this resource.',
            'code' => 403
        ]);
        exit;
    }
}