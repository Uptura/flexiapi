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
     * Add a route with a callable handler (supports legacy closure-based route files)
     */
    public function addRouteHandler(string $method, string $path, callable $handler, bool $requiresAuth = false): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $this->normalizePath($path),
            'controller' => null,
            'action' => null,
            'auth' => $requiresAuth,
            'pattern' => $this->createPattern($path),
            'handler' => $handler
        ];
    }

    // Convenience helpers for legacy route files
    public function get(string $path, callable $handler, bool $requiresAuth = true): void
    {
        $this->addRouteHandler('GET', $this->withVersionPrefix($path), $handler, $requiresAuth);
    }

    public function post(string $path, callable $handler, bool $requiresAuth = true): void
    {
        $this->addRouteHandler('POST', $this->withVersionPrefix($path), $handler, $requiresAuth);
    }

    public function put(string $path, callable $handler, bool $requiresAuth = true): void
    {
        $this->addRouteHandler('PUT', $this->withVersionPrefix($path), $handler, $requiresAuth);
    }

    public function delete(string $path, callable $handler, bool $requiresAuth = true): void
    {
        $this->addRouteHandler('DELETE', $this->withVersionPrefix($path), $handler, $requiresAuth);
    }

    /**
     * Ensure a path includes the API version prefix (v1/...) to match incoming URIs after /api is stripped.
     */
    private function withVersionPrefix(string $path): string
    {
        $p = $this->normalizePath($path);
        // If path already starts with v<number>/, leave it as-is
        if (preg_match('/^v\d+\//', $p)) {
            return $p;
        }
        // Prepend v1 by default
        return 'v1/' . $p;
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

                // If a callable handler is present, invoke it; else call controller/action
                if (isset($route['handler']) && is_callable($route['handler'])) {
                    // Pass named params if present; support both signatures
                    $handler = $route['handler'];
                    // If the route has an {id} param and handler expects one, pass it
                    if (isset($params['id'])) {
                        $handler((int)$params['id']);
                    } else {
                        $handler();
                    }
                } else {
                    // Instantiate controller and call action
                    $this->callController($route['controller'], $route['action'], $params);
                }
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
        
        // Check both Authorization and Auth-x headers
        $authHeader = $headers['Authorization'] ?? $headers['Auth-x'] ?? $headers['auth-x'] ?? '';

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