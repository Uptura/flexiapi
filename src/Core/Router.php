<?php

namespace FlexiAPI\Core;

use FlexiAPI\Utils\Response;
use FlexiAPI\Services\JWTAuth;
use PDO;

class Router
{
    private array $routes = [];
    private PDO $db;
    private array $config;
    private ?JWTAuth $jwtAuth = null;

    public function __construct(PDO $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;
        
        if (isset($config['jwt'])) {
            $this->jwtAuth = new JWTAuth(
                $config['jwt']['secret'],
                $config['jwt']['algorithm'] ?? 'HS256',
                $config['jwt']['expiration'] ?? 3600
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
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (!str_starts_with($authHeader, 'Bearer ')) {
            return false;
        }

        $token = substr($authHeader, 7);
        
        try {
            $payload = $this->jwtAuth->validateToken($token);
            // Store user info in globals for access in controllers
            $GLOBALS['authenticated_user'] = $payload;
            return true;
        } catch (\Exception $e) {
            return false;
        }
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