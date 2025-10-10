<?php

namespace FlexiAPI\Core;

use FlexiAPI\Utils\Response;
use FlexiAPI\Services\JWTAuth;
use FlexiAPI\Middleware\RateLimitMiddleware;
use FlexiAPI\DB\MySQLAdapter;
use PDO;

class FlexiAPI
{
    private array $config;
    private MySQLAdapter $db;
    private Router $router;
    private ?JWTAuth $jwtAuth = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->initializeDatabase();
        $this->initializeAuth();
        $this->router = new Router($this->db, $this->config);
        $this->loadRoutes();
    }

    public function run(): void
    {
        // Handle CORS
        $this->handleCors();
        
        // Apply rate limiting
        if (!$this->handleRateLimit()) {
            return; // Request blocked by rate limiter
        }
        
        // Get request details
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        
        // Remove /api prefix if present
        $path = preg_replace('#^/api/?#', '', $path);
        
        try {
            // Route the request
            $this->router->route($method, $path);
        } catch (\Exception $e) {
            Response::json(false, 'Internal server error', null, 500, [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function initializeDatabase(): void
    {
        try {
            $this->db = new MySQLAdapter($this->config['database']);
        } catch (\PDOException $e) {
            Response::json(false, 'Database connection failed', null, 500, [
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    private function initializeAuth(): void
    {
        if (isset($this->config['jwt'])) {
            $this->jwtAuth = new JWTAuth(
                $this->config,
                $this->db
            );
        }
    }

    private function loadRoutes(): void
    {
        // Load authentication routes
        $this->loadAuthRoutes();
        
        // Load generated endpoint routes manually
        // Users routes
        $this->router->addRoute('GET', 'v1/users', 'FlexiAPI\\Endpoints\\UsersController', 'index', true);
        $this->router->addRoute('GET', 'v1/users/search/{column}', 'FlexiAPI\\Endpoints\\UsersController', 'searchByColumn', true);
        $this->router->addRoute('POST', 'v1/users', 'FlexiAPI\\Endpoints\\UsersController', 'store', true);
        $this->router->addRoute('GET', 'v1/users/{id}', 'FlexiAPI\\Endpoints\\UsersController', 'show', true);
        $this->router->addRoute('PUT', 'v1/users/{id}', 'FlexiAPI\\Endpoints\\UsersController', 'update', true);
        $this->router->addRoute('DELETE', 'v1/users/{id}', 'FlexiAPI\\Endpoints\\UsersController', 'destroy', true);
        
        // Products routes
        $this->router->addRoute('GET', 'v1/products', 'FlexiAPI\\Endpoints\\ProductsController', 'index', true);
        $this->router->addRoute('GET', 'v1/products/search/{column}', 'FlexiAPI\\Endpoints\\ProductsController', 'searchByColumn', true);
        $this->router->addRoute('POST', 'v1/products', 'FlexiAPI\\Endpoints\\ProductsController', 'store', true);
        $this->router->addRoute('GET', 'v1/products/{id}', 'FlexiAPI\\Endpoints\\ProductsController', 'show', true);
        $this->router->addRoute('PUT', 'v1/products/{id}', 'FlexiAPI\\Endpoints\\ProductsController', 'update', true);
        $this->router->addRoute('DELETE', 'v1/products/{id}', 'FlexiAPI\\Endpoints\\ProductsController', 'destroy', true);
    }

    private function loadAuthRoutes(): void
    {
        // Add auth routes
        $this->router->addRoute('POST', 'v1/auth/generate_keys', 'FlexiAPI\\Controllers\\AuthController', 'generateKeys', false);
        $this->router->addRoute('POST', 'v1/auth/login', 'FlexiAPI\\Controllers\\AuthController', 'login', false);
        $this->router->addRoute('POST', 'v1/auth/refresh', 'FlexiAPI\\Controllers\\AuthController', 'refresh', true);
        $this->router->addRoute('POST', 'v1/auth/logout', 'FlexiAPI\\Controllers\\AuthController', 'logout', true);
    }

    private function handleCors(): void
    {
        $origins = $this->config['cors']['origins'] ?? ['*'];
        $methods = $this->config['cors']['methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
        $headers = $this->config['cors']['headers'] ?? ['Content-Type', 'Auth-x', 'X-API-Key'];

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (in_array('*', $origins) || in_array($origin, $origins)) {
            header("Access-Control-Allow-Origin: " . ($origin ?: '*'));
        }

        header("Access-Control-Allow-Methods: " . implode(', ', $methods));
        header("Access-Control-Allow-Headers: " . implode(', ', $headers));
        header("Access-Control-Allow-Credentials: true");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    private function handleRateLimit(): bool
    {
        $rateLimiter = new RateLimitMiddleware($this->config);
        return $rateLimiter->handle();
    }

    public function getDatabase(): MySQLAdapter
    {
        return $this->db;
    }

    public function getAuth(): ?JWTAuth
    {
        return $this->jwtAuth;
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}