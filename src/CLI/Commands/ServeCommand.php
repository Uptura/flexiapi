<?php

namespace FlexiAPI\CLI\Commands;

class ServeCommand extends BaseCommand
{
    protected string $signature = 'serve';
    protected string $description = 'Start the FlexiAPI development server';
    
    private string $logFile;
    private bool $isRunning = false;
    
    public function execute(array $args): int
    {
        $this->handle($args);
        return 0;
    }
    
    public function handle(array $args): void
    {
        $this->output("🚀 FlexiAPI Development Server", 'header');
        $this->output("");
        
        // Parse arguments
        $host = $this->getOption($args, '--host', '127.0.0.1');
        $port = (int)$this->getOption($args, '--port', '8000');
        $docroot = $this->getOption($args, '--docroot', 'public');
        $verbose = $this->hasOption($args, '--verbose') || $this->hasOption($args, '-v');
        
        // Validate configuration
        if (!$this->validateSetup($docroot)) {
            return;
        }
        
        // Setup logging
        $this->setupLogging();
        
        // Display server info
        $this->displayServerInfo($host, $port, $docroot, $verbose);
        
        // Start the server
        $this->startServer($host, $port, $docroot, $verbose);
    }
    
    private function getOption(array $args, string $option, string $default = ''): string
    {
        $key = array_search($option, $args);
        if ($key !== false && isset($args[$key + 1])) {
            return $args[$key + 1];
        }
        return $default;
    }
    
    private function hasOption(array $args, string $option): bool
    {
        return in_array($option, $args);
    }
    
    private function validateSetup(string $docroot): bool
    {
        // Get the actual document root path
        $fullDocroot = $this->workingDir . DIRECTORY_SEPARATOR . $docroot;
        
        // Check if public directory exists
        if (!is_dir($fullDocroot)) {
            $this->output("❌ Document root '{$fullDocroot}' not found!", 'error');
            $this->output("   Run: flexiapi setup", 'info');
            return false;
        }
        
        // Check if index.php exists
        $indexFile = $fullDocroot . DIRECTORY_SEPARATOR . 'index.php';
        if (!file_exists($indexFile)) {
            $this->output("❌ Entry point '{$indexFile}' not found!", 'error');
            $this->output("   Run: flexiapi setup", 'info');
            return false;
        }
        
        // Check if config exists
        $configPath = $this->workingDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
        if (!file_exists($configPath)) {
            $this->output("❌ Configuration not found!", 'error');
            $this->output("   Run: flexiapi setup", 'info');
            return false;
        }
        
        // Check if any endpoints exist
        $endpointsDir = $this->workingDir . DIRECTORY_SEPARATOR . 'endpoints';
        if (!is_dir($endpointsDir) || empty(glob($endpointsDir . DIRECTORY_SEPARATOR . '*Controller.php'))) {
            $this->output("⚠️  No endpoints found. Create some endpoints first:", 'yellow');
            $this->output("   flexiapi create users", 'info');
            $this->output("");
        }
        
        return true;
    }
    
    private function setupLogging(): void
    {
        $logsDir = $this->workingDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
        
        $this->logFile = $logsDir . DIRECTORY_SEPARATOR . 'server_' . date('Y-m-d') . '.log';
        
        // Initialize log file
        $this->writeLog("=== FlexiAPI Development Server Started ===");
        $this->writeLog("Timestamp: " . date('Y-m-d H:i:s'));
        $this->writeLog("PID: " . getmypid());
    }
    
    private function writeLog(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($this->logFile, "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
    }
    
    private function displayServerInfo(string $host, int $port, string $docroot, bool $verbose): void
    {
        $config = $this->loadConfig();
        
        $this->output("📊 Server Configuration:", 'blue');
        $this->output("   Host: {$host}");
        $this->output("   Port: {$port}");
        $this->output("   Document Root: {$docroot}");
        $this->output("   Database: " . ($config['database']['host'] ?? 'Not configured'));
        $this->output("   Verbose: " . ($verbose ? 'Enabled' : 'Disabled'));
        $this->output("");
        
        $this->output("🌐 API Endpoints:", 'blue');
        $this->output("   Base URL: http://{$host}:{$port}/api/" . ($config['api']['version'] ?? 'v1'));
        $this->output("   Authentication: http://{$host}:{$port}/api/v1/auth/");
        
        // List available endpoints
        $endpoints = $this->getAvailableEndpoints();
        if (!empty($endpoints)) {
            $this->output("   Endpoints:");
            foreach ($endpoints as $endpoint) {
                $this->output("     • http://{$host}:{$port}/api/v1/{$endpoint}", 'cyan');
            }
        }
        $this->output("");
        
        $this->output("📝 Log File: {$this->logFile}", 'info');
        $this->output("");
        
        $this->output("🎯 Quick Test Commands:", 'blue');
        $this->output("   curl http://{$host}:{$port}/api/v1/auth/generate_keys");
        if (!empty($endpoints)) {
            $firstEndpoint = $endpoints[0];
            $this->output("   curl http://{$host}:{$port}/api/v1/{$firstEndpoint}");
        }
        $this->output("");
        
        $this->output("⚡ Starting server at http://{$host}:{$port}", 'green');
        $this->output("   Press Ctrl+C to stop", 'yellow');
        $this->output("");
    }
    
    private function getAvailableEndpoints(): array
    {
        $endpoints = [];
        
        $endpointsDir = $this->workingDir . DIRECTORY_SEPARATOR . 'endpoints';
        if (is_dir($endpointsDir)) {
            $controllers = glob($endpointsDir . DIRECTORY_SEPARATOR . '*Controller.php');
            foreach ($controllers as $controller) {
                $name = basename($controller, 'Controller.php');
                $endpoints[] = strtolower($name);
            }
        }
        
        return $endpoints;
    }
    
    private function startServer(string $host, int $port, string $docroot, bool $verbose): void
    {
        // Get the full document root path
        $fullDocroot = $this->workingDir . DIRECTORY_SEPARATOR . $docroot;
        
        // Create router file for better URL handling
        $routerFile = $this->createRouterFile($fullDocroot);
        
        // Build PHP built-in server command
        $cmd = "php -S {$host}:{$port} -t \"{$fullDocroot}\"";
        
        if ($verbose) {
            $cmd .= " \"{$routerFile}\"";
        } else {
            // For Windows, redirect stderr to null differently
            if (PHP_OS_FAMILY === 'Windows') {
                $cmd .= " \"{$routerFile}\" 2>nul";
            } else {
                $cmd .= " \"{$routerFile}\" 2>/dev/null";
            }
        }
        
        // Set up signal handling for graceful shutdown
        $this->setupSignalHandling();
        
        // Start server in a way that allows us to capture output
        $this->isRunning = true;
        
        if ($verbose) {
            $this->output("🔧 Command: {$cmd}", 'cyan');
            $this->output("");
        }
        
        // Log server start
        $this->writeLog("Server started on {$host}:{$port}");
        $this->writeLog("Document root: {$fullDocroot}");
        $this->writeLog("Verbose mode: " . ($verbose ? 'enabled' : 'disabled'));
        
        // Execute the server command
        $descriptors = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR
        ];
        
        $process = proc_open($cmd, $descriptors, $pipes, $this->workingDir);
        
        if (is_resource($process)) {
            // Wait for the process to finish
            $status = proc_close($process);
            
            $this->writeLog("Server stopped with status: {$status}");
            $this->output("\n🛑 Server stopped.", 'yellow');
        } else {
            $this->output("❌ Failed to start server!", 'error');
            $this->writeLog("Failed to start server");
        }
        
        // Cleanup
        if (file_exists($routerFile)) {
            unlink($routerFile);
        }
    }
    
    private function createRouterFile(string $docroot): string
    {
        $routerFile = $docroot . DIRECTORY_SEPARATOR . 'router.php';
        
        // Calculate the relative path from docroot to storage/logs
        $storageLogsPath = $this->workingDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        
        $routerContent = <<<PHP
<?php
// FlexiAPI Development Server Router

\$requestUri = \$_SERVER['REQUEST_URI'];
\$requestMethod = \$_SERVER['REQUEST_METHOD'];
\$scriptName = \$_SERVER['SCRIPT_NAME'];

// Log request
\$logEntry = date('Y-m-d H:i:s') . " [{\$requestMethod}] {\$requestUri}\\n";
\$logDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
if (!is_dir(\$logDir)) {
    mkdir(\$logDir, 0755, true);
}
file_put_contents(\$logDir . DIRECTORY_SEPARATOR . 'access_' . date('Y-m-d') . '.log', \$logEntry, FILE_APPEND | LOCK_EX);

// Remove query string
\$path = parse_url(\$requestUri, PHP_URL_PATH);

// Serve static files directly
if (\$path !== '/' && file_exists(__DIR__ . \$path)) {
    return false; // Let PHP built-in server handle static files
}

// Handle API routes through index.php
if (strpos(\$path, '/api/') === 0) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'index.php';
    return true;
}

// Handle root request
if (\$path === '/') {
    echo json_encode([
        'message' => 'FlexiAPI Development Server',
        'version' => '3.7.1',
        'timestamp' => date('c'),
        'endpoints' => [
            'auth' => '/api/v1/auth/',
            'docs' => '/api/v1/docs',
            'status' => '/api/v1/status'
        ]
    ], JSON_PRETTY_PRINT);
    return true;
}

// Handle docs
if (\$path === '/api/v1/docs' || \$path === '/docs') {
    echo json_encode([
        'message' => 'FlexiAPI Documentation',
        'base_url' => 'http://' . \$_SERVER['HTTP_HOST'] . '/api/v1',
        'authentication' => [
            'jwt' => 'POST /api/v1/auth/login',
            'api_key' => 'POST /api/v1/auth/generate_keys'
        ],
        'endpoints' => []
    ], JSON_PRETTY_PRINT);
    return true;
}

// Handle status
if (\$path === '/api/v1/status' || \$path === '/status') {
    echo json_encode([
        'status' => 'running',
        'timestamp' => date('c'),
        'server' => 'FlexiAPI Development Server',
        'php_version' => phpversion(),
        'memory_usage' => memory_get_usage(true),
        'uptime' => 'N/A (development mode)'
    ], JSON_PRETTY_PRINT);
    return true;
}

// Default: serve through index.php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'index.php';
return true;
PHP;
        
        file_put_contents($routerFile, $routerContent);
        return $routerFile;
    }
    
    private function setupSignalHandling(): void
    {
        // Handle Ctrl+C gracefully (if available)
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function() {
                $this->output("\n🛑 Received shutdown signal...", 'yellow');
                $this->writeLog("Received shutdown signal");
                $this->isRunning = false;
            });
        }
    }
    
    protected function loadConfig(): array
    {
        $configFile = $this->workingDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
        if (file_exists($configFile)) {
            return require $configFile;
        }
        
        return [
            'api' => ['version' => 'v1'],
            'database' => ['host' => 'Not configured']
        ];
    }
}