<?php

namespace FlexiAPI\Core;

use FlexiAPI\Utils\Response;
use FlexiAPI\Services\JWTAuth;
use FlexiAPI\DB\MySQLAdapter;
use PDO;

class Router
{
    private array $routes = [];
    private MySQLAdapter $db;
    private array $config;
    private ?JWTAuth $jwtAuth = null;

    public function __construct(MySQLAdapter $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;
        
        if (isset($config['jwt'])) {
            $this->jwtAuth = new JWTAuth(
                $config,
                $this->db
            );
        }
    }

    /**
     * Add a route
     */
    public function addRoute(string $method, string $path, string $controller, string $action, bool $requiresAuth = false): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $this->normalizePath($path),
            'controller' => $controller,
            'action' => $action,
            'auth' => $requiresAuth,
            'pattern' => $this->createPattern($path)
        ];
    }

    /**
     * Route a request to the appropriate controller
     */
    public function route(string $method, string $path): void
    {
        $method = strtoupper($method);
        $path = $this->normalizePath($path);

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $path, $matches)) {
                // Check authentication if required
                if ($route['auth'] && !$this->checkAuthentication()) {
                    Response::json(false, 'Authentication required', null, 401);
                    return;
                }

                // Extract parameters from URL
                $params = $this->extractParams($matches);

                // Instantiate controller and call action
                $this->callController($route['controller'], $route['action'], $params);
                return;
            }
        }

        // No route found
        Response::json(false, 'Route not found', null, 404);
    }

    /**
     * Create a regex pattern from a route path
     */
    private function createPattern(string $path): string
    {
        // Normalize path
        $path = $this->normalizePath($path);
        
        // Convert {param} to named capture groups
        $pattern = preg_replace('/\{([^}]+)\}/', '(?P<$1>[^/]+)', $path);
        
        // Escape forward slashes and create full pattern
        $pattern = str_replace('/', '\/', $pattern);
        
        return '/^' . $pattern . '$/';
    }

    /**
     * Normalize path by removing leading/trailing slashes
     */
    private function normalizePath(string $path): string
    {
        return trim($path, '/');
    }

    /**
     * Extract parameters from route matches
     */
    private function extractParams(array $matches): array
    {
        $params = [];
        foreach ($matches as $key => $value) {
            if (!is_numeric($key)) {
                $params[$key] = $value;
            }
        }
        return $params;
    }

    /**
     * Check if request is authenticated
     */
    private function checkAuthentication(): bool
    {
        // Check JWT token
        if ($this->jwtAuth && $this->checkJWTAuth()) {
            return true;
        }

        // Check API key
        return $this->checkApiKey();
    }

    /**
     * Check JWT authentication
     */
    private function checkJWTAuth(): bool
    {
        // Get headers using multiple methods for better compatibility
        $authHeader = $this->getAuthHeader();

        if (!str_starts_with($authHeader, 'Bearer ')) {
            // Debug: log non-Bearer headers for PUT
            if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
                error_log("PUT: Auth header found but not Bearer format: '$authHeader'");
            }
            return false;
        }

        $token = substr($authHeader, 7);
        
        // Debug: log token validation for PUT
        if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            error_log("PUT: Attempting to validate token: " . substr($token, 0, 20) . "...");
        }
        
        try {
            $payload = $this->jwtAuth->validateToken($token);
            // Store user info in globals for access in controllers
            $GLOBALS['authenticated_user'] = $payload;
            
            if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
                error_log("PUT: JWT validation successful");
            }
            return true;
        } catch (\Exception $e) {
            if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
                error_log("PUT: JWT validation failed: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Get Auth header using multiple methods for better compatibility
     */
    private function getAuthHeader(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
        
        // Special handling for PUT requests - they might have different header parsing
        if ($method === 'PUT') {
            return $this->getPutAuthHeader();
        }
        
        // For other methods, use standard approach
        return $this->getStandardAuthHeader();
    }

    /**
     * Get auth header specifically for PUT requests
     */
    private function getPutAuthHeader(): string
    {
        // Method 1: Check all possible $_SERVER variations for PUT
        $serverKeys = [
            'HTTP_AUTHORIZATION',
            'HTTP_AUTH_X', 
            'REDIRECT_HTTP_AUTHORIZATION',
            'REDIRECT_HTTP_AUTH_X',
            'HTTP_X_AUTH'
        ];
        
        foreach ($serverKeys as $key) {
            if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
                return $_SERVER[$key];
            }
        }
        
        // Method 2: Try getallheaders with case variations
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            $authHeaders = ['Authorization', 'authorization', 'Auth-x', 'auth-x', 'AUTH-X', 'AUTHORIZATION'];
            
            foreach ($authHeaders as $headerName) {
                if (isset($headers[$headerName])) {
                    return $headers[$headerName];
                }
            }
        }
        
        // Method 3: Parse from raw HTTP input (last resort for PUT)
        return $this->parseAuthFromRawInput();
    }

    /**
     * Get auth header for standard requests (GET, POST, etc.)
     */
    private function getStandardAuthHeader(): string
    {
        // Method 1: Try getallheaders() if available
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            
            // Try different case variations - PRIORITIZE STANDARD Authorization header first
            foreach (['Authorization', 'authorization', 'Auth-x', 'auth-x', 'AUTH-X'] as $headerName) {
                if (isset($headers[$headerName])) {
                    return $headers[$headerName];
                }
            }
        }
        
        // Method 2: Check $_SERVER variables
        $serverKeys = [
            'HTTP_AUTHORIZATION',     // Standard first
            'HTTP_AUTH_X',
            'REDIRECT_HTTP_AUTHORIZATION',
            'REDIRECT_HTTP_AUTH_X'
        ];
        
        foreach ($serverKeys as $key) {
            if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
                return $_SERVER[$key];
            }
        }
        
        return '';
    }

    /**
     * Parse auth header from raw HTTP input (for PUT requests)
     */
    private function parseAuthFromRawInput(): string
    {
        // This is a last resort method for PUT requests
        // Check if we can extract headers from the raw request
        
        // Try to get from Content-Type or other headers that might contain auth info
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        // Look for Authorization in various forms
        foreach ($_SERVER as $key => $value) {
            if (stripos($key, 'AUTH') !== false || stripos($key, 'AUTHORIZATION') !== false) {
                if (!empty($value) && stripos($value, 'Bearer') !== false) {
                    return $value;
                }
            }
        }
        
        return '';
    }

    /**
     * Check API key authentication
     */
    private function checkApiKey(): bool
    {
        $headers = getallheaders();
        $apiKey = $headers['X-API-Key'] ?? '';

        if (empty($apiKey)) {
            return false;
        }

        try {
            $stmt = $this->db->prepare("SELECT id, user_id FROM api_keys WHERE api_key = ? AND is_active = 1");
            $stmt->execute([$apiKey]);
            $result = $stmt->fetch();

            if ($result) {
                // Store API key info in globals
                $GLOBALS['authenticated_api_key'] = $result;
                return true;
            }
        } catch (\PDOException $e) {
            // If api_keys table doesn't exist, skip API key auth
        }

        return false;
    }

    /**
     * Call the appropriate controller method
     */
    private function callController(string $controllerClass, string $action, array $params): void
    {
        if (!class_exists($controllerClass)) {
            Response::json(false, 'Controller not found', null, 500);
            return;
        }

        $controller = new $controllerClass($this->db, $this->config);

        if (!method_exists($controller, $action)) {
            Response::json(false, 'Action not found', null, 500);
            return;
        }

        // Call the action with parameters
        if (!empty($params)) {
            // For actions that expect an ID parameter
            if (isset($params['id']) && method_exists($controller, $action)) {
                $controller->$action((int)$params['id']);
            } else {
                $controller->$action($params);
            }
        } else {
            $controller->$action();
        }
    }

    /**
     * Get all registered routes (for debugging)
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}