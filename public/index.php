<?php
chdir(dirname(__DIR__)); // Force working directory to project root
require_once __DIR__ . '/../vendor/autoload.php';

use FlexiAPI\Core\FlexiAPI;

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