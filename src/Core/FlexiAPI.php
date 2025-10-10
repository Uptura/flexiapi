<?php

namespace FlexiAPI\Core;

use FlexiAPI\Utils\Response;
use FlexiAPI\Services\JWTAuth;
use FlexiAPI\Middleware\RateLimitMiddleware;
use PDO;

class FlexiAPI
{
    private array $config;
    private PDO $db;
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
            $dsn = "mysql:host={$this->config['database']['host']};port={$this->config['database']['port']};dbname={$this->config['database']['database']};charset={$this->config['database']['charset']}";
            
            $this->db = new PDO(
                $dsn,
                $this->config['database']['username'],
                $this->config['database']['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
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
                $this->config['jwt']['secret'],
                $this->config['jwt']['algorithm'] ?? 'HS256',
                $this->config['jwt']['expiration'] ?? 3600
            );
        }
    }

    private function loadRoutes(): void
    {
        // Load authentication routes
        $this->loadAuthRoutes();
        
        // Load generated endpoint routes from api/routes directory
        $routesDir = 'api/routes';
        if (is_dir($routesDir)) {
            foreach (glob($routesDir . '/*.php') as $routeFile) {
                $routes = require $routeFile;
                if (is_array($routes)) {
                    foreach ($routes as $route) {
                        $this->router->addRoute(
                            $route['method'],
                            $route['path'],
                            $route['controller'],
                            $route['action'],
                            $route['auth'] ?? false
                        );
                    }
                }
            }
        }
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
        $headers = $this->config['cors']['headers'] ?? ['Content-Type', 'Authorization', 'X-API-Key'];

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

    public function getDatabase(): PDO
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