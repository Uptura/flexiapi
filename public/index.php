<?php

require_once __DIR__ . '/../vendor/autoload.php';

use FlexiAPI\Core\FlexiAPI;

// Handle CORS
// Load CORS configuration
$corsConfigPath = __DIR__ . '/../config/cors.php';
if (file_exists($corsConfigPath)) {
    $corsConfig = require $corsConfigPath;
    
    // Set CORS headers
    header('Access-Control-Allow-Origin: ' . implode(', ', $corsConfig['origins']));
    header('Access-Control-Allow-Methods: ' . implode(', ', $corsConfig['methods']));
    header('Access-Control-Allow-Headers: ' . implode(', ', $corsConfig['headers']));
    header('Access-Control-Allow-Credentials: ' . ($corsConfig['credentials'] ? 'true' : 'false'));
    header('Access-Control-Max-Age: ' . $corsConfig['max_age']);
} else {
    // Fallback CORS headers
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, Auth-x');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load configuration
$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration not found. Run: flexiapi setup']);
    exit;
}

$config = require $configPath;

// Initialize and run the API
try {
    $api = new FlexiAPI($config);
    $api->run();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}