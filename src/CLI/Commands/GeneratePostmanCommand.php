<?php

namespace FlexiAPI\CLI\Commands;

class GeneratePostmanCommand extends BaseCommand
{
    protected string $signature = 'generate:postman';
    protected string $description = 'Generate Postman collection for all API endpoints';
    
    public function execute(array $args): int
    {
        $this->handle($args);
        return 0;
    }
    
    public function handle(array $args): void
    {
        $this->output("📮 Generating Postman Collection", 'header');
        $this->output("");
        
        // Load configuration
        $config = $this->loadConfig();
        $baseUrl = $config['api']['base_url'] ?? 'http://localhost:8000/api';
        $version = $config['api']['version'] ?? 'v1';
        
        // Collect all endpoints
        $endpoints = $this->discoverEndpoints();
        
        if (empty($endpoints)) {
            $this->output("❌ No endpoints found! Create some endpoints first.", 'error');
            $this->output("   Use: php bin/flexiapi create:endpoint <name>", 'info');
            return;
        }
        
        $this->output("🔍 Found " . count($endpoints) . " endpoint(s):", 'info');
        foreach ($endpoints as $endpoint) {
            $this->output("   • {$endpoint}", 'cyan');
        }
        $this->output("");
        
        // Generate collection
        $collection = $this->generateCollection($endpoints, $baseUrl, $version);
        
        // Save collection
        $outputFile = $this->saveCollection($collection);
        
        $this->output("✅ Postman collection generated successfully!", 'green');
        $this->output("📁 Saved to: {$outputFile}", 'info');
        $this->output("");
        $this->showImportInstructions();
    }
    
    private function discoverEndpoints(): array
    {
        $endpoints = [];
        
        if (!is_dir('endpoints')) {
            return $endpoints;
        }
        
        $controllers = glob('endpoints/*Controller.php');
        
        foreach ($controllers as $controller) {
            $name = basename($controller, 'Controller.php');
            $endpointName = strtolower($name);
            
            // Skip if SQL file doesn't exist
            if (file_exists("sql/{$endpointName}.sql")) {
                $endpoints[] = $endpointName;
            }
        }
        
        return $endpoints;
    }
    
    private function generateCollection(array $endpoints, string $baseUrl, string $version): array
    {
        $collection = [
            'info' => [
                'name' => 'FlexiAPI Collection',
                'description' => 'Auto-generated Postman collection for FlexiAPI endpoints',
                'version' => '1.0.0',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'
            ],
            'auth' => [
                'type' => 'bearer',
                'bearer' => [
                    [
                        'key' => 'token',
                        'value' => '{{jwt_token}}',
                        'type' => 'string'
                    ]
                ]
            ],
            'variable' => [
                [
                    'key' => 'base_url',
                    'value' => $baseUrl,
                    'type' => 'string'
                ],
                [
                    'key' => 'jwt_token',
                    'value' => '',
                    'type' => 'string'
                ],
                [
                    'key' => 'api_key',
                    'value' => '',
                    'type' => 'string'
                ]
            ],
            'item' => []
        ];
        
        // Add authentication folder
        $collection['item'][] = $this->generateAuthFolder($baseUrl, $version);
        
        // Add endpoint folders
        foreach ($endpoints as $endpoint) {
            $collection['item'][] = $this->generateEndpointFolder($endpoint, $baseUrl, $version);
        }
        
        return $collection;
    }
    
    private function generateAuthFolder(string $baseUrl, string $version): array
    {
        return [
            'name' => '🔐 Authentication',
            'description' => 'Authentication endpoints for JWT tokens and API keys',
            'item' => [
                [
                    'name' => 'Generate API Key',
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            [
                                'key' => 'Content-Type',
                                'value' => 'application/json',
                                'type' => 'text'
                            ]
                        ],
                        'body' => [
                            'mode' => 'raw',
                            'raw' => json_encode([
                                'secret_key' => '{{api_secret_key}}',
                                'user_id' => 1,
                                'name' => 'My Application'
                            ], JSON_PRETTY_PRINT)
                        ],
                        'url' => [
                            'raw' => "{{base_url}}/{$version}/auth/generate_keys",
                            'host' => ['{{base_url}}'],
                            'path' => [$version, 'auth', 'generate_keys']
                        ],
                        'description' => 'Generate a new API key for authentication'
                    ]
                ],
                [
                    'name' => 'Login (JWT)',
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            [
                                'key' => 'Content-Type',
                                'value' => 'application/json',
                                'type' => 'text'
                            ]
                        ],
                        'body' => [
                            'mode' => 'raw',
                            'raw' => json_encode([
                                'email' => 'user@example.com',
                                'password' => 'password123'
                            ], JSON_PRETTY_PRINT)
                        ],
                        'url' => [
                            'raw' => "{{base_url}}/{$version}/auth/login",
                            'host' => ['{{base_url}}'],
                            'path' => [$version, 'auth', 'login']
                        ],
                        'description' => 'Login and receive JWT token'
                    ]
                ],
                [
                    'name' => 'Refresh Token',
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            [
                                'key' => 'Authorization',
                                'value' => 'Bearer {{jwt_token}}',
                                'type' => 'text'
                            ]
                        ],
                        'url' => [
                            'raw' => "{{base_url}}/{$version}/auth/refresh",
                            'host' => ['{{base_url}}'],
                            'path' => [$version, 'auth', 'refresh']
                        ],
                        'description' => 'Refresh JWT token'
                    ]
                ],
                [
                    'name' => 'Logout',
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            [
                                'key' => 'Authorization',
                                'value' => 'Bearer {{jwt_token}}',
                                'type' => 'text'
                            ]
                        ],
                        'url' => [
                            'raw' => "{{base_url}}/{$version}/auth/logout",
                            'host' => ['{{base_url}}'],
                            'path' => [$version, 'auth', 'logout']
                        ],
                        'description' => 'Logout (client-side token removal)'
                    ]
                ]
            ]
        ];
    }
    
    private function generateEndpointFolder(string $endpoint, string $baseUrl, string $version): array
    {
        $endpointTitle = ucfirst($endpoint);
        $sampleData = $this->generateSampleData($endpoint);
        
        return [
            'name' => "📊 {$endpointTitle}",
            'description' => "CRUD operations for {$endpoint} endpoint",
            'item' => [
                [
                    'name' => "List {$endpointTitle}",
                    'request' => [
                        'method' => 'GET',
                        'header' => [
                            [
                                'key' => 'Authorization',
                                'value' => 'Bearer {{jwt_token}}',
                                'type' => 'text'
                            ]
                        ],
                        'url' => [
                            'raw' => "{{base_url}}/{$version}/{$endpoint}?page=1&limit=20",
                            'host' => ['{{base_url}}'],
                            'path' => [$version, $endpoint],
                            'query' => [
                                ['key' => 'page', 'value' => '1'],
                                ['key' => 'limit', 'value' => '20'],
                                ['key' => 'search', 'value' => '', 'disabled' => true],
                                ['key' => 'sort', 'value' => 'id', 'disabled' => true],
                                ['key' => 'order', 'value' => 'DESC', 'disabled' => true]
                            ]
                        ],
                        'description' => "Get paginated list of {$endpoint} with optional filtering"
                    ]
                ],
                [
                    'name' => "Create {$endpointTitle}",
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            [
                                'key' => 'Content-Type',
                                'value' => 'application/json',
                                'type' => 'text'
                            ],
                            [
                                'key' => 'Authorization',
                                'value' => 'Bearer {{jwt_token}}',
                                'type' => 'text'
                            ]
                        ],
                        'body' => [
                            'mode' => 'raw',
                            'raw' => json_encode($sampleData, JSON_PRETTY_PRINT)
                        ],
                        'url' => [
                            'raw' => "{{base_url}}/{$version}/{$endpoint}",
                            'host' => ['{{base_url}}'],
                            'path' => [$version, $endpoint]
                        ],
                        'description' => "Create a new {$endpoint} record"
                    ]
                ],
                [
                    'name' => "Get {$endpointTitle} by ID",
                    'request' => [
                        'method' => 'GET',
                        'header' => [
                            [
                                'key' => 'Authorization',
                                'value' => 'Bearer {{jwt_token}}',
                                'type' => 'text'
                            ]
                        ],
                        'url' => [
                            'raw' => "{{base_url}}/{$version}/{$endpoint}/1",
                            'host' => ['{{base_url}}'],
                            'path' => [$version, $endpoint, '1']
                        ],
                        'description' => "Get a specific {$endpoint} record by ID"
                    ]
                ],
                [
                    'name' => "Update {$endpointTitle}",
                    'request' => [
                        'method' => 'PUT',
                        'header' => [
                            [
                                'key' => 'Content-Type',
                                'value' => 'application/json',
                                'type' => 'text'
                            ],
                            [
                                'key' => 'Authorization',
                                'value' => 'Bearer {{jwt_token}}',
                                'type' => 'text'
                            ]
                        ],
                        'body' => [
                            'mode' => 'raw',
                            'raw' => json_encode($sampleData, JSON_PRETTY_PRINT)
                        ],
                        'url' => [
                            'raw' => "{{base_url}}/{$version}/{$endpoint}/1",
                            'host' => ['{{base_url}}'],
                            'path' => [$version, $endpoint, '1']
                        ],
                        'description' => "Update an existing {$endpoint} record"
                    ]
                ],
                [
                    'name' => "Delete {$endpointTitle}",
                    'request' => [
                        'method' => 'DELETE',
                        'header' => [
                            [
                                'key' => 'Authorization',
                                'value' => 'Bearer {{jwt_token}}',
                                'type' => 'text'
                            ]
                        ],
                        'url' => [
                            'raw' => "{{base_url}}/{$version}/{$endpoint}/1",
                            'host' => ['{{base_url}}'],
                            'path' => [$version, $endpoint, '1']
                        ],
                        'description' => "Delete a {$endpoint} record by ID"
                    ]
                ]
            ]
        ];
    }
    
    private function generateSampleData(string $endpoint): array
    {
        $columns = $this->getEndpointColumns($endpoint);
        $sampleData = [];
        
        foreach ($columns as $column) {
            $value = $this->generateSampleValue($column['name'], $column['type']);
            if ($value !== null) {
                $sampleData[$column['name']] = $value;
            }
        }
        
        return $sampleData ?: ['name' => 'Sample Name', 'description' => 'Sample Description'];
    }
    
    private function getEndpointColumns(string $endpoint): array
    {
        $sqlFile = "sql/{$endpoint}.sql";
        if (!file_exists($sqlFile)) {
            return [];
        }
        
        $content = file_get_contents($sqlFile);
        preg_match_all('/`(\w+)` ([^,\n]+)/i', $content, $matches);
        
        $columns = [];
        for ($i = 0; $i < count($matches[1]); $i++) {
            $columnName = $matches[1][$i];
            $columnType = trim($matches[2][$i]);
            
            // Skip system columns
            if (in_array($columnName, ['id', 'created_at', 'updated_at'])) {
                continue;
            }
            
            $columns[] = [
                'name' => $columnName,
                'type' => $columnType
            ];
        }
        
        return $columns;
    }
    
    private function generateSampleValue(string $columnName, string $columnType): mixed
    {
        $columnName = strtolower($columnName);
        $columnType = strtoupper($columnType);
        
        // Based on column name
        if (str_contains($columnName, 'email')) {
            return 'user@example.com';
        }
        if (str_contains($columnName, 'name')) {
            return 'Sample Name';
        }
        if (str_contains($columnName, 'description')) {
            return 'Sample description';
        }
        if (str_contains($columnName, 'price')) {
            return 99.99;
        }
        if (str_contains($columnName, 'quantity') || str_contains($columnName, 'count')) {
            return 10;
        }
        if (str_contains($columnName, 'status')) {
            return 'active';
        }
        if (str_contains($columnName, 'date') || str_contains($columnName, 'time')) {
            return date('Y-m-d H:i:s');
        }
        if (str_contains($columnName, 'url') || str_contains($columnName, 'link')) {
            return 'https://example.com';
        }
        
        // Based on column type
        if (str_contains($columnType, 'VARCHAR') || str_contains($columnType, 'TEXT')) {
            return 'Sample text';
        }
        if (str_contains($columnType, 'INT')) {
            return 123;
        }
        if (str_contains($columnType, 'DECIMAL') || str_contains($columnType, 'FLOAT')) {
            return 123.45;
        }
        if (str_contains($columnType, 'BOOLEAN') || str_contains($columnType, 'BOOL')) {
            return true;
        }
        if (str_contains($columnType, 'DATETIME') || str_contains($columnType, 'TIMESTAMP')) {
            return date('Y-m-d H:i:s');
        }
        if (str_contains($columnType, 'JSON')) {
            return ['key' => 'value'];
        }
        
        return 'sample_value';
    }
    
    private function saveCollection(array $collection): string
    {
        // Create postman directory if it doesn't exist
        if (!is_dir('postman')) {
            mkdir('postman', 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "FlexiAPI_Collection_{$timestamp}.json";
        $filepath = "postman/{$filename}";
        
        // Save collection
        file_put_contents($filepath, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        // Also save as latest
        file_put_contents('postman/FlexiAPI_Collection_Latest.json', json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        return $filepath;
    }
    
    private function showImportInstructions(): void
    {
        $this->output("📋 Import Instructions:", 'blue');
        $this->output("1. Open Postman application");
        $this->output("2. Click 'Import' button");
        $this->output("3. Select the generated JSON file");
        $this->output("4. Configure environment variables:");
        $this->output("   • base_url: Your API base URL");
        $this->output("   • jwt_token: JWT token from login");
        $this->output("   • api_key: Generated API key");
        $this->output("   • api_secret_key: Your API secret key");
        $this->output("");
        $this->output("🔧 Environment Setup:", 'blue');
        $this->output("Create a new environment in Postman with these variables:");
        $this->output("   base_url = http://localhost:8000/api");
        $this->output("   jwt_token = (leave empty, will be set after login)");
        $this->output("   api_key = (leave empty, will be set after generation)");
        $this->output("   api_secret_key = " . ($this->loadConfig()['api']['secret_key'] ?? 'your_secret_key'));
        $this->output("");
    }
    
    private function loadConfig(): array
    {
        $configFile = 'config/config.php';
        if (file_exists($configFile)) {
            return require $configFile;
        }
        
        return [
            'api' => [
                'base_url' => 'http://localhost:8000/api',
                'version' => 'v1',
                'secret_key' => 'your_secret_key'
            ]
        ];
    }
}