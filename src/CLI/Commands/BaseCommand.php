<?php

namespace FlexiAPI\CLI\Commands;

use FlexiAPI\CLI\Console;

abstract class BaseCommand
{
    protected Console $console;
    protected string $workingDir;
    
    public function __construct()
    {
        $this->console = new Console();
        $this->workingDir = getcwd();
        $this->ensureDirectories();
    }
    
    abstract public function execute(array $args): int;
    
    protected function ensureDirectories(): void
    {
        $directories = [
            'endpoints',
            'sql',
            'config',
            'generated',
            'logs'
        ];
        
        foreach ($directories as $dir) {
            $path = $this->workingDir . DIRECTORY_SEPARATOR . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }
    
    protected function output(string $message, string $type = 'normal'): void
    {
        $this->console->output($message, $type);
    }
    
    protected function input(string $prompt): string
    {
        return $this->console->input($prompt);
    }
    
    protected function ask(string $question, string $default = ''): string
    {
        $prompt = $question;
        if (!empty($default)) {
            $prompt .= " [$default]";
        }
        $prompt .= ": ";
        
        $this->output($prompt, 'question');
        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        fclose($handle);
        
        return empty($input) ? $default : $input;
    }
    
    protected function askSecret(string $question): string
    {
        $this->output($question . ": ", 'question');
        
        // Hide input for password/secret
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            $handle = fopen("php://stdin", "r");
            $input = trim(fgets($handle));
            fclose($handle);
        } else {
            // Unix/Linux/Mac
            system('stty -echo');
            $handle = fopen("php://stdin", "r");
            $input = trim(fgets($handle));
            fclose($handle);
            system('stty echo');
            echo "\n";
        }
        
        return $input;
    }
    
    protected function createDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            return mkdir($path, 0755, true);
        }
        return true;
    }
    
    protected function confirm(string $message): bool
    {
        return $this->console->confirm($message);
    }
    
    protected function getConfigPath(): string
    {
        return $this->workingDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
    }
    
    protected function loadConfig(): array
    {
        $configPath = $this->getConfigPath();
        if (!file_exists($configPath)) {
            return $this->getDefaultConfig();
        }
        
        // Load PHP config file instead of JSON
        $config = require $configPath;
        return is_array($config) ? $config : $this->getDefaultConfig();
    }
    
    protected function saveConfig(array $config): bool
    {
        // Save core config to .env file (database, jwt, encryption, api settings)
        if (isset($config['database']) || isset($config['jwt']) || isset($config['encryption']) || isset($config['api'])) {
            $this->saveEnvConfig($config);
        }
        
        // Save config.php with getenv() template + any endpoint configurations
        $configPath = $this->getConfigPath();
        $phpConfig = $this->generateConfigTemplate($config);
        return file_put_contents($configPath, $phpConfig) !== false;
    }
    
    private function saveEnvConfig(array $config): void
    {
        $envContent = '';
        
        // Only update .env if we have core configuration data
        if (isset($config['database'])) {
            $envContent .= "# FlexiAPI Environment Configuration\n";
            $envContent .= "DB_HOST=" . ($config['database']['host'] ?? 'localhost') . "\n";
            $envContent .= "DB_PORT=" . ($config['database']['port'] ?? '3306') . "\n";
            $envContent .= "DB_DATABASE=" . ($config['database']['database'] ?? '') . "\n";
            $envContent .= "DB_USERNAME=" . ($config['database']['username'] ?? '') . "\n";
            $envContent .= "DB_PASSWORD=" . ($config['database']['password'] ?? '') . "\n";
        }
        
        if (isset($config['jwt'])) {
            $envContent .= "JWT_SECRET=" . ($config['jwt']['secret'] ?? '') . "\n";
        }
        
        if (isset($config['encryption'])) {
            $envContent .= "ENCRYPTION_KEY=" . ($config['encryption']['key'] ?? '') . "\n";
        }
        
        if (isset($config['api'])) {
            $envContent .= "API_SECRET_KEY=" . ($config['api']['secret_key'] ?? '') . "\n";
        }
        
        if (!empty($envContent)) {
            file_put_contents('.env', $envContent);
        }
    }
    
    private function generateConfigTemplate(array $config): string
    {
        // Always use getenv() template for core settings
        $coreConfig = <<<'PHP'
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
        'charset' => 'utf8mb4'
    ],
    'jwt' => [
        'secret' => getenv('JWT_SECRET'),
        'algorithm' => 'HS256',
        'expiration' => 3600
    ],
    'encryption' => [
        'key' => getenv('ENCRYPTION_KEY')
    ],
    'api' => [
        'secret_key' => getenv('API_SECRET_KEY'),
        'base_url' => 'http://localhost:8000/api',
        'version' => 'v1'
    ],
    'rate_limit' => [
        'enabled' => %s,
        'requests_per_minute' => %d,
        'storage' => 'file'
    ],
    'cors' => [
        'origins' => %s,
        'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'headers' => ['Content-Type', 'Authorization', 'X-API-Key']
    ]%s
];
PHP;

        // Handle rate limiting settings
        $rateLimitEnabled = isset($config['rate_limit']['enabled']) ? 
            ($config['rate_limit']['enabled'] ? 'true' : 'false') : 'false';
        $rateLimitRequests = $config['rate_limit']['requests_per_minute'] ?? 60;
        
        // Handle CORS origins
        $corsOrigins = isset($config['cors']['origins']) ? 
            $this->arrayToPhpString($config['cors']['origins']) : "['*']";
        
        // Handle endpoints section
        $endpointsSection = '';
        if (isset($config['endpoints']) && !empty($config['endpoints'])) {
            $endpointsSection = ",\n    'endpoints' => " . $this->arrayToPhpString($config['endpoints'], 1);
        }
        
        return sprintf($coreConfig, $rateLimitEnabled, $rateLimitRequests, $corsOrigins, $endpointsSection);
    }
    
    protected function getDefaultConfig(): array
    {
        return [
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
                'database' => '',
                'username' => '',
                'password' => '',
                'charset' => 'utf8mb4'
            ],
            'jwt' => [
                'secret' => bin2hex(random_bytes(32)),
                'algorithm' => 'HS256',
                'expiration' => 3600
            ],
            'encryption' => [
                'key' => bin2hex(random_bytes(32))
            ],
            'api' => [
                'secret_key' => bin2hex(random_bytes(16)),
                'base_url' => 'http://localhost:8000/api',
                'version' => 'v1'
            ],
            'rate_limit' => [
                'enabled' => true,
                'requests_per_minute' => 60,
                'storage' => 'file'
            ],
            'cors' => [
                'origins' => ['*'],
                'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
                'headers' => ['Content-Type', 'Authorization', 'X-API-Key']
            ]
        ];
    }
    
    /**
     * Convert array to PHP string format for config files
     */
    private function arrayToPhpString(array $array, int $indent = 0): string
    {
        $spaces = str_repeat('    ', $indent);
        $result = "[\n";
        
        foreach ($array as $key => $value) {
            $result .= $spaces . "    ";
            
            if (is_string($key)) {
                $result .= "'" . addslashes($key) . "' => ";
            }
            
            if (is_array($value)) {
                $result .= $this->arrayToPhpString($value, $indent + 1);
            } elseif (is_string($value)) {
                $result .= "'" . addslashes($value) . "'";
            } elseif (is_bool($value)) {
                $result .= $value ? 'true' : 'false';
            } elseif (is_null($value)) {
                $result .= 'null';
            } else {
                $result .= $value;
            }
            
            $result .= ",\n";
        }
        
        $result .= $spaces . "]";
        return $result;
    }
    
    protected function getEndpointsPath(): string
    {
        return $this->workingDir . DIRECTORY_SEPARATOR . 'endpoints';
    }
    
    protected function getSqlPath(): string
    {
        return $this->workingDir . DIRECTORY_SEPARATOR . 'sql';
    }
    
    protected function getGeneratedPath(): string
    {
        return $this->workingDir . DIRECTORY_SEPARATOR . 'generated';
    }
    
    protected function validateEndpointName(string $name): bool
    {
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name);
    }
    
    protected function sanitizeColumnName(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', trim($name));
    }
    
    protected function generateMd5Hash(string $data): string
    {
        return md5($data);
    }
}