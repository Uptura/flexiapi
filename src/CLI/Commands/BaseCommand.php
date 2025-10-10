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
        return $this->workingDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'flexiapi.json';
    }
    
    protected function loadConfig(): array
    {
        $configPath = $this->getConfigPath();
        if (!file_exists($configPath)) {
            return $this->getDefaultConfig();
        }
        
        $config = json_decode(file_get_contents($configPath), true);
        return $config ?: $this->getDefaultConfig();
    }
    
    protected function saveConfig(array $config): bool
    {
        $configPath = $this->getConfigPath();
        return file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT)) !== false;
    }
    
    protected function getDefaultConfig(): array
    {
        return [
            'database' => [
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => '',
                'username' => '',
                'password' => '',
                'charset' => 'utf8mb4'
            ],
            'auth' => [
                'secret_key' => bin2hex(random_bytes(32)),
                'token_expiration' => 3600,
                'algorithm' => 'HS256'
            ],
            'rate_limiting' => [
                'enabled' => true,
                'requests_per_minute' => 60,
                'requests_per_hour' => 1000
            ],
            'encryption' => [
                'key' => bin2hex(random_bytes(16))
            ],
            'endpoints' => []
        ];
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