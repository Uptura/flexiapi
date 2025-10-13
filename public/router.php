<?php
// FlexiAPI Development Server Router

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];
$scriptName = $_SERVER['SCRIPT_NAME'];

// Log request
$logEntry = date('Y-m-d H:i:s') . " [{$requestMethod}] {$requestUri}\n";
$logDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
file_put_contents($logDir . DIRECTORY_SEPARATOR . 'access_' . date('Y-m-d') . '.log', $logEntry, FILE_APPEND | LOCK_EX);

// Remove query string
$path = parse_url($requestUri, PHP_URL_PATH);

// Serve static files directly
if ($path !== '/' && file_exists(__DIR__ . $path)) {
    return false; // Let PHP built-in server handle static files
}

// Handle API routes through index.php
if (strpos($path, '/api/') === 0) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'index.php';
    return true;
}

// Handle root request
if ($path === '/') {
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
if ($path === '/api/v1/docs' || $path === '/docs') {
    echo json_encode([
        'message' => 'FlexiAPI Documentation',
        'base_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/api/v1',
        'authentication' => [
            'jwt' => 'POST /api/v1/auth/login',
            'api_key' => 'POST /api/v1/auth/generate_keys'
        ],
        'endpoints' => []
    ], JSON_PRETTY_PRINT);
    return true;
}

// Handle status
if ($path === '/api/v1/status' || $path === '/status') {
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