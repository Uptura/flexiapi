<?php

namespace FlexiAPI\CLI\Commands;

class SetupCommand extends BaseCommand
{
    protected string $signature = 'setup';
    protected string $description = 'Setup and configure FlexiAPI framework';
    
    public function execute(array $args): int
    {
        $this->handle();
        return 0;
    }
    
    public function handle(): void
    {
        $this->output("\n🚀 Welcome to FlexiAPI Framework Setup!\n", 'green');
        $this->output("Let's configure your API development environment.\n");
        
        // Check if config already exists
        if (file_exists($this->getConfigPath())) {
            $overwrite = $this->confirm("Configuration already exists. Do you want to overwrite it?");
            if (!$overwrite) {
                $this->output("Setup cancelled.", 'yellow');
                return;
            }
        }
        
        // Collect configuration data
        $config = $this->collectConfiguration();
        
        // Create directories
        $this->createDirectories();
        
        // Save configuration
        $this->saveConfiguration($config);
        
        // Create initial files
        $this->createInitialFiles();
        
        // Create default CORS configuration
        $this->createDefaultCorsConfig();
        
        $this->output("\n✅ FlexiAPI setup completed successfully!\n", 'green');
        $this->showGettingStarted();
    }
    
    private function collectConfiguration(): array
    {
        $this->output("\n📊 Database Configuration", 'blue');
        $this->output("Please provide your MySQL database connection details:\n");
        
        $dbHost = $this->ask("Database Host", "localhost");
        $dbPort = $this->ask("Database Port", "3306");
        $dbName = $this->ask("Database Name");
        $dbUser = $this->ask("Database Username");
        $dbPass = $this->askSecret("Database Password");
        
        $this->output("\n🔐 Security Configuration", 'blue');
        
        $jwtSecret = $this->ask("JWT Secret Key (leave empty to generate)", "");
        if (empty($jwtSecret)) {
            $jwtSecret = bin2hex(random_bytes(32));
            $this->output("Generated JWT Secret: " . substr($jwtSecret, 0, 10) . "...", 'yellow');
        }
        
        $encryptionKey = $this->ask("Encryption Key (leave empty to generate)", "");
        if (empty($encryptionKey)) {
            $encryptionKey = bin2hex(random_bytes(32));
            $this->output("Generated Encryption Key: " . substr($encryptionKey, 0, 10) . "...", 'yellow');
        }
        
        $secretKey = $this->ask("API Secret Key (for key generation endpoint)", bin2hex(random_bytes(16)));
        
        $this->output("\n⚙️ API Configuration", 'blue');
        
        $rateLimitRequests = (int)$this->ask("Rate Limit - Requests per minute", "60");
        $rateLimitEnabled = $this->confirm("Enable rate limiting?", true);
        
        $corsOrigins = $this->ask("CORS Origins (comma-separated, * for all)", "*");
        
        return [
            'database' => [
                'host' => $dbHost,
                'port' => (int)$dbPort,
                'database' => $dbName,
                'username' => $dbUser,
                'password' => $dbPass,
                'charset' => 'utf8mb4'
            ],
            'jwt' => [
                'secret' => $jwtSecret,
                'algorithm' => 'HS256',
                'expiration' => 3600
            ],
            'encryption' => [
                'key' => $encryptionKey
            ],
            'api' => [
                'secret_key' => $secretKey,
                'base_url' => 'http://localhost:8000/api',
                'version' => 'v1'
            ],
            'rate_limit' => [
                'enabled' => $rateLimitEnabled,
                'requests_per_minute' => $rateLimitRequests,
                'storage' => 'file'
            ],
            'cors' => [
                'origins' => array_map('trim', explode(',', $corsOrigins)),
                'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
                'headers' => ['Content-Type', 'Authorization', 'X-API-Key']
            ]
        ];
    }
    
    private function createDirectories(): void
    {
        $directories = [
            'config',
            'storage',
            'storage/logs',
            'storage/cache',
            'storage/uploads',
            'api',
            'api/controllers',
            'api/routes',
            'api/sql',
            'postman'
        ];
        
        foreach ($directories as $dir) {
            $this->createDirectory($dir);
        }
        
        $this->output("📁 Created project directories", 'green');
    }
    
    private function createInitialFiles(): void
    {
        // Create .htaccess for Apache
        $htaccess = <<<'HTACCESS'
RewriteEngine On

# Handle CORS preflight requests
RewriteCond %{REQUEST_METHOD} OPTIONS
RewriteRule ^(.*)$ $1 [R=200,L]

# Route all API requests to index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/(.*)$ public/index.php [QSA,L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
HTACCESS;
        
        file_put_contents('.htaccess', $htaccess);
        
        // Create public/index.php entry point
        $indexPhp = <<<'PHP'
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
PHP;
        
        if (!file_exists('public')) {
            mkdir('public', 0755, true);
        }
        file_put_contents('public/index.php', $indexPhp);
        
        // Create example .env file
        $envExample = <<<'ENV'
# FlexiAPI Environment Configuration
# Copy this file to .env and update with your values

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

JWT_SECRET=your_jwt_secret_here
ENCRYPTION_KEY=your_encryption_key_here
API_SECRET_KEY=your_api_secret_here

RATE_LIMIT_ENABLED=true
RATE_LIMIT_REQUESTS=60

CORS_ORIGINS=*
ENV;
        
        file_put_contents('.env.example', $envExample);
        
        // Create README.md
        $readme = <<<'README'
# FlexiAPI Framework

A powerful CLI-based API development framework for rapid endpoint creation.

## Quick Start

1. **Setup the framework:**
   ```bash
   flexiapi setup
   ```

2. **Create your first endpoint:**
   ```bash
   flexiapi create:endpoint users
   ```

3. **Start the development server:**
   ```bash
   flexiapi serve
   ```

## Available Commands

- `flexiapi setup` - Initial framework setup
- `flexiapi create:endpoint <name>` - Create a new API endpoint
- `flexiapi update:endpoint <name>` - Update an existing endpoint
- `flexiapi generate:postman` - Generate Postman collection
- `flexiapi export:sql` - Export all SQL schemas

## Documentation

Your API endpoints will be available at:
- `GET /api/v1/{endpoint}` - List all records
- `POST /api/v1/{endpoint}` - Create new record
- `GET /api/v1/{endpoint}/{id}` - Get specific record
- `PUT /api/v1/{endpoint}/{id}` - Update record
- `DELETE /api/v1/{endpoint}/{id}` - Delete record

## Authentication

Use the `/api/v1/auth/generate_keys` endpoint to generate API keys for authentication.

## Features

- ✅ Rapid endpoint creation
- ✅ JWT Authentication
- ✅ Data validation
- ✅ Encryption support
- ✅ Rate limiting
- ✅ CORS handling
- ✅ Postman collection generation
- ✅ SQL export functionality
README;
        
        file_put_contents('README.md', $readme);
        
        // Create Procfile for PaaS deployments (Heroku, Railway, etc.)
        $procfile = <<<'PROCFILE'
web: php -S 0.0.0.0:$PORT -t public/app
PROCFILE;
        
        file_put_contents('Procfile', $procfile);
        
        // Create .nixpacks.toml for Railway and other Nixpacks platforms
        $nixpacks = <<<'NIXPACKS'
[start]
cmd = "php -S 0.0.0.0:8080 -t /app/public"
NIXPACKS;
        
        file_put_contents('.nixpacks.toml', $nixpacks);
        
        $this->output("📄 Created initial project files", 'green');
    }
    
    private function showGettingStarted(): void
    {
        $this->output("🎯 Getting Started Guide:", 'blue');
        $this->output("");
        $this->output("1. Create your first endpoint:");
        $this->output("   flexiapi create:endpoint users", 'cyan');
        $this->output("");
        $this->output("2. Start the development server:");
        $this->output("   flexiapi serve", 'cyan');
        $this->output("");
        $this->output("3. Test your API:");
        $this->output("   curl http://localhost:8000/api/v1/users", 'cyan');
        $this->output("");
        $this->output("4. Generate Postman collection:");
        $this->output("   flexiapi generate:postman", 'cyan');
        $this->output("");
        $this->output("📚 Check README.md for detailed documentation!");
        $this->output("");
    }
    
    private function saveConfiguration(array $config): void
    {
        // Save to config/config.php
        $originsStr = "'" . implode("', '", $config['cors']['origins']) . "'";
        $methodsStr = "'" . implode("', '", $config['cors']['methods']) . "'";
        $headersStr = "'" . implode("', '", $config['cors']['headers']) . "'";
        
        $configPhp = <<<PHP
<?php

return [
    'database' => [
        'host' => '{$config['database']['host']}',
        'port' => {$config['database']['port']},
        'database' => '{$config['database']['database']}',
        'username' => '{$config['database']['username']}',
        'password' => '{$config['database']['password']}',
        'charset' => '{$config['database']['charset']}'
    ],
    'jwt' => [
        'secret' => '{$config['jwt']['secret']}',
        'algorithm' => '{$config['jwt']['algorithm']}',
        'expiration' => {$config['jwt']['expiration']}
    ],
    'encryption' => [
        'key' => '{$config['encryption']['key']}'
    ],
    'api' => [
        'secret_key' => '{$config['api']['secret_key']}',
        'base_url' => '{$config['api']['base_url']}',
        'version' => '{$config['api']['version']}'
    ],
    'rate_limit' => [
        'enabled' => {$this->boolToString($config['rate_limit']['enabled'])},
        'requests_per_minute' => {$config['rate_limit']['requests_per_minute']},
        'storage' => '{$config['rate_limit']['storage']}'
    ],
    'cors' => [
        'origins' => [{$originsStr}],
        'methods' => [{$methodsStr}],
        'headers' => [{$headersStr}]
    ]
];
PHP;

        file_put_contents('config/config.php', $configPhp);
        $this->output("💾 Configuration saved to config/config.php", 'green');
    }
    
    private function boolToString(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
    
    private function createDefaultCorsConfig(): void
    {
        $corsConfig = <<<'PHP'
<?php

/**
 * CORS Configuration for FlexiAPI
 * Generated during setup
 */

return [
    'origins' => ['*'],
    'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'headers' => ['Content-Type', 'Authorization', 'X-API-Key', 'Auth-x'],
    'credentials' => false,
    'max_age' => 86400
];
PHP;
        
        file_put_contents('config/cors.php', $corsConfig);
        $this->output("🌐 Default CORS configuration created", 'green');
    }
}