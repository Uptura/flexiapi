#!/usr/bin/env php
<?php
/**
 * FlexiAPI Postman Collection Generator
 *
 * Usage:
 *   php bin/generate_postman.php > postman_collection.json
 *   or simply: vendor/bin/generate_postman
 */

// 🔹 Load Autoloader (works both in dev and installed package)
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php', // local
    __DIR__ . '/../../../autoload.php'   // after composer install
];
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require $path;
        break;
    }
}

use FlexiAPI\Controllers\CustomController;
use FlexiAPI\DB\MySQLAdapter;

// 🔹 Load Config
$configPath = getcwd() . '/config/config.php'; // user project
if (!file_exists($configPath)) {
    $configPath = __DIR__ . '/../config/config.php'; // fallback for library
}
$config = require $configPath;

// 🔹 Globals
$baseUrl = '{{baseUrl}}';
$apiKey  = '{{apiKey}}';
$headers = [['key' => 'X-FlexiAPI-Key', 'value' => $apiKey]];

// 🔹 Postman Collection Base
$collection = [
    'info' => [
        'name' => 'FlexiAPI Collection',
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'
    ],
    'item' => []
];

// 🔹 CRUD Examples
$examples = [
    'Create (Insert Rows)' => [
        'method' => 'POST',
        'url' => $baseUrl . '/index.php',
        'body' => [
            "table" => "users",
            "data" => [
                ["name" => "Jane", "email" => "jane@example.com", "age" => 22],
                ["name" => "Paul", "email" => "paul@example.com", "age" => 29]
            ]
        ]
    ],
    'Get (Select Rows)' => [
        'method' => 'GET',
        'url' => $baseUrl . '/index.php',
        'query' => [
            ["key" => "table", "value" => "users"],
            ["key" => "columns", "value" => "id,name,email"],
            ["key" => "condition", "value" => '[{"field":"status","operator":"=","value":"active"}]'],
            ["key" => "page", "value" => "1"],
            ["key" => "limit", "value" => "5"]
        ]
    ],
    'Update Rows' => [
        'method' => 'PUT',
        'url' => $baseUrl . '/index.php',
        'body' => [
            "table" => "users",
            "where" => ["field" => "id", "operator" => "=", "value" => 1],
            "data" => ["status" => "inactive"]
        ]
    ],
    'Delete Rows' => [
        'method' => 'DELETE',
        'url' => $baseUrl . '/index.php',
        'body' => [
            "table" => "users",
            "column" => "id",
            "values" => [2],
            "limit" => 1
        ]
    ]
];

// 🔹 Try DB Connection (optional)
try {
    $db = new MySQLAdapter($config['db']);
} catch (\Throwable $e) {
    $db = null;
}

// 🔹 Build CRUD Requests
foreach ($examples as $name => $ex) {
    $item = [
        'name' => $name,
        'request' => [
            'method' => $ex['method'],
            'header' => $headers,
            'url' => [
                'raw' => $ex['url'],
                'host' => [$baseUrl],
                'path' => ['index.php']
            ]
        ]
    ];

    if (isset($ex['query'])) {
        $item['request']['url']['query'] = $ex['query'];
    }

    if (isset($ex['body'])) {
        $item['request']['body'] = [
            'mode' => 'raw',
            'raw' => json_encode($ex['body'], JSON_PRETTY_PRINT)
        ];
        $item['request']['header'][] = [
            'key' => 'Content-Type',
            'value' => 'application/json'
        ];
    }

    $collection['item'][] = $item;
}

// 🔹 Add Custom Methods (if available)
if ($db) {
    $customController = new CustomController($db, $config);
    $customMethods = $customController->listMethods();

    foreach ($customMethods as $method) {
        $collection['item'][] = [
            'name' => "Custom: {$method}",
            'request' => [
                'method' => 'POST',
                'header' => array_merge($headers, [
                    ['key' => 'Content-Type', 'value' => 'application/json']
                ]),
                'url' => [
                    'raw' => $baseUrl . '/index.php',
                    'host' => [$baseUrl],
                    'path' => ['index.php']
                ],
                'body' => [
                    'mode' => 'raw',
                    'raw' => json_encode([
                        "action" => "custom",
                        "method" => $method,
                        "params" => new \stdClass()
                    ], JSON_PRETTY_PRINT)
                ]
            ]
        ];
    }
}

// 🔹 Output Result
echo json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
