<?php

/**
 * FlexiAPI Configuration Example
 * 
 * Copy this file to config.php and customize your settings.
 * This file should contain your actual database credentials and API settings.
 * 
 * IMPORTANT: Never commit config.php to version control!
 * Add config.php to your .gitignore file.
 */

return [
    // ================================
    // Database Configuration
    // ================================
    'database' => [
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => $_ENV['DB_PORT'] ?? 3306,
        'dbname' => $_ENV['DB_DATABASE'] ?? 'flexiapi',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ],

    // ================================
    // Authentication & Security
    // ================================
    'authentication' => [
        'method' => 'api_key', // api_key, jwt, session, none
        'required' => false,
        'jwt_secret' => $_ENV['JWT_SECRET'] ?? 'your-jwt-secret-here',
        'jwt_expiry' => 3600, // seconds
        'api_secret_key' => $_ENV['API_SECRET_KEY'] ?? 'your-secret-key-here',
        'encryption_key' => $_ENV['ENCRYPTION_KEY'] ?? 'your-encryption-key-here',
    ],

    // ================================
    // Rate Limiting
    // ================================
    'rate_limiting' => [
        'enabled' => $_ENV['RATE_LIMIT_ENABLED'] ?? true,
        'requests_per_minute' => $_ENV['RATE_LIMIT_REQUESTS'] ?? 100,
        'storage' => 'file', // file, memory
        'storage_path' => 'storage/rate_limits/',
        'cleanup_interval' => 3600, // seconds
    ],

    // ================================
    // CORS Configuration
    // ================================
    'cors' => [
        'enabled' => true,
        'allowed_origins' => $_ENV['CORS_ORIGINS'] ?? '*',
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
        'expose_headers' => ['X-Total-Count', 'X-Page-Count'],
        'max_age' => 86400, // 24 hours
    ],

    // ================================
    // Application Settings
    // ================================
    'app' => [
        'name' => 'FlexiAPI',
        'version' => '2.0.0',
        'environment' => $_ENV['APP_ENV'] ?? 'development',
        'debug' => $_ENV['APP_DEBUG'] ?? true,
        'timezone' => 'UTC',
        'locale' => 'en',
    ],

    // ================================
    // API Configuration
    // ================================
    'api' => [
        'version' => 'v1',
        'prefix' => 'api',
        'default_limit' => 50,
        'max_limit' => 1000,
        'response_format' => 'json',
        'include_meta' => true,
    ],

    // ================================
    // Logging
    // ================================
    'logging' => [
        'enabled' => true,
        'level' => $_ENV['LOG_LEVEL'] ?? 'info', // debug, info, warning, error
        'file' => 'storage/logs/flexiapi.log',
        'max_file_size' => '10MB',
        'rotate_files' => 5,
        'sql_logging' => false,
    ],

    // ================================
    // Cache Configuration
    // ================================
    'cache' => [
        'enabled' => true,
        'driver' => 'file', // file, memory
        'path' => 'storage/cache/',
        'ttl' => 3600, // seconds
        'query_cache' => true,
    ],

    // ================================
    // File Storage
    // ================================
    'storage' => [
        'path' => 'storage/',
        'uploads_path' => 'storage/uploads/',
        'max_upload_size' => '10M',
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
    ],

    // ================================
    // Email Configuration
    // ================================
    'email' => [
        'enabled' => false,
        'host' => $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com',
        'port' => $_ENV['MAIL_PORT'] ?? 587,
        'username' => $_ENV['MAIL_USERNAME'] ?? '',
        'password' => $_ENV['MAIL_PASSWORD'] ?? '',
        'encryption' => 'tls', // tls, ssl
        'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@yourdomain.com',
        'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'FlexiAPI',
    ],

    // ================================
    // Development Server
    // ================================
    'dev_server' => [
        'host' => $_ENV['DEV_SERVER_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['DEV_SERVER_PORT'] ?? 8000,
        'verbose' => $_ENV['DEV_SERVER_VERBOSE'] ?? false,
        'access_log' => 'storage/logs/access.log',
        'error_log' => 'storage/logs/error.log',
    ],

    // ================================
    // Security Headers
    // ================================
    'security' => [
        'headers' => [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ],
        'https_only' => false, // Set to true in production
        'secure_cookies' => false, // Set to true in production
    ],

    // ================================
    // Database Schema
    // ================================
    'schema' => [
        'auto_timestamps' => true,
        'soft_deletes' => false,
        'default_charset' => 'utf8mb4',
        'default_collation' => 'utf8mb4_unicode_ci',
    ],

    // ================================
    // Performance Settings
    // ================================
    'performance' => [
        'max_execution_time' => 30,
        'memory_limit' => '256M',
        'query_timeout' => 30,
        'connection_pool_size' => 10,
    ],

    // ================================
    // Validation Rules
    // ================================
    'validation' => [
        'strict_mode' => true,
        'max_string_length' => 255,
        'max_text_length' => 65535,
        'date_format' => 'Y-m-d H:i:s',
        'timezone_validation' => true,
    ],

    // ================================
    // Export Settings
    // ================================
    'export' => [
        'sql_path' => 'exports/',
        'postman_path' => 'postman_collections/',
        'include_sample_data' => false,
        'max_sample_records' => 100,
    ],
];