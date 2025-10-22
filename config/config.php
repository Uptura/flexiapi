<?php


require_once 'loadenv.php';

loadEnv(__DIR__ . '/../.env');

return [
    'database' => [
        'host' => getenv('DB_HOST'),
        'port' => getenv('DB_PORT') ?? 3306,
        'database' => getenv('DB_DATABASE'),
        'username' => getenv('DB_USERNAME'),
        'password' => getenv('DB_PASSWORD'),
        'charset' => getenv('DB_CHARSET') ?? 'utf8mb4'
    ],
    'jwt' => [
        'secret' => getenv('JWT_SECRET'),
        'algorithm' => getenv('JWT_ALGORITHM'),
        'expiration' => getenv('JWT_EXPIRATION')
    ],
    'encryption' => [
        'key' => getenv('ENCRYPTION_KEY')
    ],
    'api' => [
        'secret_key' => getenv('API_SECRET_KEY'),
        'base_url' => getenv('API_BASE_URL'),
        'version' => getenv('API_VERSION')
    ],
    'rate_limit' => [
        'enabled' => false,
        'requests_per_minute' => 60,
        'storage' => 'file'
    ],
    'cors' => [
        'origins' => ['*'],
        'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'headers' => ['Content-Type', 'Authorization', 'X-API-Key']
    ]
];