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

        // Register dev-only debug route for route inspection
        if (method_exists($this->router, 'registerDebugRoute')) {
            $this->router->registerDebugRoute();
        }

        // Auto-register endpoint controllers discovered via PSR-4 in endpoints/
        $this->autoRegisterEndpointControllers();

        // Auto-include all closure-based *Routes.php files in endpoints/
        $this->includeLegacyRouteFiles();
    }
    /**
     * Include all *Routes.php files in endpoints/ for legacy/custom route support
     */
    private function includeLegacyRouteFiles(): void
    {
        $endpointsDir = $this->getProjectRoot() . DIRECTORY_SEPARATOR . 'endpoints';
        if (!is_dir($endpointsDir)) {
            return;
        }
        
        // First, load all controller files to ensure classes are available
        $controllerFiles = glob($endpointsDir . DIRECTORY_SEPARATOR . '*Controller.php');
        foreach ($controllerFiles as $controllerFile) {
            if (file_exists($controllerFile)) {
                require_once $controllerFile;
            }
        }
        
        // Then include route files
        $files = glob($endpointsDir . DIRECTORY_SEPARATOR . '*Routes.php');
        foreach ($files as $file) {
            // Provide $router, $db, $config in scope for included files
            $router = $this->router;
            $db = $this->db;
            $config = $this->config;
            include $file;
        }
    }

    private function loadAuthRoutes(): void
    {
        // Add default auth routes
        $this->router->addRoute('POST', 'v1/auth/generate_keys', 'FlexiAPI\\Controllers\\AuthController', 'generateKeys', false);
        $this->router->addRoute('POST', 'v1/auth/refresh', 'FlexiAPI\\Controllers\\AuthController', 'refresh', true);
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

    /**
     * Detect the correct project root directory
     * In Composer installations: /path/to/project (where composer.json exists)
     * In development: /path/to/flexiapi (where we are now)
     */
    private function getProjectRoot(): string
    {
        // Start from this file's directory
        $currentDir = dirname(__DIR__, 2); // Go to package root
        
        // Check if we're in a vendor directory (Composer installation)
        if (strpos($currentDir, 'vendor' . DIRECTORY_SEPARATOR) !== false) {
            // We're installed via Composer, find the project root
            $parts = explode(DIRECTORY_SEPARATOR, $currentDir);
            $vendorIndex = array_search('vendor', $parts);
            if ($vendorIndex !== false) {
                // Project root is one level up from vendor
                $projectRoot = implode(DIRECTORY_SEPARATOR, array_slice($parts, 0, $vendorIndex));
                return $projectRoot;
            }
        }
        
        // We're in development mode, use current working directory
        return getcwd() ?: $currentDir;
    }

    /**
     * Automatically discover endpoint controllers under FlexiAPI\\Endpoints
     * and register conventional CRUD routes for each.
     * This avoids manual edits and keeps Composer installs flexible.
     */
    private function autoRegisterEndpointControllers(): void
    {
        // Attempt to list classes by scanning endpoints directory
        $endpointsDir = $this->getProjectRoot() . DIRECTORY_SEPARATOR . 'endpoints';
        
        if (!is_dir($endpointsDir)) {
            return;
        }

        $files = glob($endpointsDir . DIRECTORY_SEPARATOR . '*Controller.php');
        
        foreach ($files as $file) {
            $classBase = basename($file, '.php'); // e.g., UsersController
            $fqcn = 'FlexiAPI\\Endpoints\\' . $classBase;

            // Manually include the controller file before checking if class exists
            if (file_exists($file)) {
                require_once $file;
            }

            // Ensure class is loadable
            if (!class_exists($fqcn)) {
                // composer dump-autoload -o may be required, but skip silently
                continue;
            }

            // Derive endpoint path from class name (e.g., UsersController -> users)
            $endpoint = strtolower(preg_replace('/Controller$/', '', $classBase));
            if ($endpoint === '') {
                continue;
            }

            // Register conventional CRUD routes
            $this->router->addRoute('GET', "v1/{$endpoint}", $fqcn, 'index', true);
            $this->router->addRoute('GET', "v1/{$endpoint}/search/{column}", $fqcn, 'searchByColumn', true);
            $this->router->addRoute('POST', "v1/{$endpoint}", $fqcn, 'store', true);
            $this->router->addRoute('GET', "v1/{$endpoint}/{id}", $fqcn, 'show', true);
            $this->router->addRoute('PUT', "v1/{$endpoint}/{id}", $fqcn, 'update', true);
            $this->router->addRoute('DELETE', "v1/{$endpoint}/{id}", $fqcn, 'destroy', true);
        }
    }
}