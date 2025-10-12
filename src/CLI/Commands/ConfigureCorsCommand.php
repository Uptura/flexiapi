<?php

namespace FlexiAPI\CLI\Commands;

use FlexiAPI\CLI\Commands\BaseCommand;

class ConfigureCorsCommand extends BaseCommand
{
    protected string $name = 'configure:cors';
    protected string $description = 'Configure CORS (Cross-Origin Resource Sharing) policy';

    public function execute(array $args = []): int
    {
        $this->output("🌐 FlexiAPI CORS Configuration");
        $this->output("====================================");

        // Check if config directory exists
        $configDir = $this->getProjectRoot() . '/config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $corsConfigPath = $configDir . '/cors.php';
        
        // Load existing CORS config if it exists
        $currentConfig = $this->loadCurrentCorsConfig($corsConfigPath);
        
        $this->output("\nCurrent CORS Configuration:");
        $this->displayCurrentConfig($currentConfig);
        
        $this->output("\n" . str_repeat("-", 50));
        $this->output("Configure new CORS settings (press Enter to keep current value):");
        
        // Interactive configuration
        $newConfig = $this->interactiveConfiguration($currentConfig);
        
        // Save configuration
        $this->saveCorsConfig($corsConfigPath, $newConfig);
        
        // Update index.php to use CORS config
        $this->updateIndexPhp($newConfig);
        
        $this->output("\n✅ CORS configuration updated successfully!");
        $this->output("📁 Configuration saved to: config/cors.php");
        $this->output("🔄 index.php updated to use new CORS settings");
        
        $this->output("\n🚀 Your API now supports the following CORS policy:");
        $this->displayFinalConfig($newConfig);
        
        return 0;
    }

    private function loadCurrentCorsConfig(string $configPath): array
    {
        if (file_exists($configPath)) {
            return require $configPath;
        }
        
        // Default CORS configuration
        return [
            'origins' => ['*'],
            'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            'headers' => ['Content-Type', 'Authorization', 'X-API-Key', 'Auth-x'],
            'credentials' => false,
            'max_age' => 86400 // 24 hours
        ];
    }

    private function displayCurrentConfig(array $config): void
    {
        $this->output("  Origins: " . implode(', ', $config['origins']));
        $this->output("  Methods: " . implode(', ', $config['methods']));
        $this->output("  Headers: " . implode(', ', $config['headers']));
        $this->output("  Credentials: " . ($config['credentials'] ? 'true' : 'false'));
        $this->output("  Max Age: " . $config['max_age'] . " seconds");
    }

    private function interactiveConfiguration(array $currentConfig): array
    {
        $config = $currentConfig;

        // Configure Origins
        $this->output("\n1. 🌍 Allowed Origins");
        $this->output("   Current: " . implode(', ', $currentConfig['origins']));
        $this->output("   Examples: *, https://yourdomain.com, http://localhost:3000");
        $originsInput = $this->prompt("   Enter allowed origins (comma-separated)");
        
        if (!empty($originsInput)) {
            $config['origins'] = array_map('trim', explode(',', $originsInput));
        }

        // Configure Methods
        $this->output("\n2. 🔧 Allowed Methods");
        $this->output("   Current: " . implode(', ', $currentConfig['methods']));
        $this->output("   Available: GET, POST, PUT, DELETE, OPTIONS, PATCH, HEAD");
        $methodsInput = $this->prompt("   Enter allowed methods (comma-separated)");
        
        if (!empty($methodsInput)) {
            $config['methods'] = array_map('trim', explode(',', $methodsInput));
        }

        // Configure Headers
        $this->output("\n3. 📋 Allowed Headers");
        $this->output("   Current: " . implode(', ', $currentConfig['headers']));
        $this->output("   Common: Content-Type, Authorization, X-API-Key, Auth-x, Accept");
        $headersInput = $this->prompt("   Enter allowed headers (comma-separated)");
        
        if (!empty($headersInput)) {
            $config['headers'] = array_map('trim', explode(',', $headersInput));
        }

        // Configure Credentials
        $this->output("\n4. 🔐 Allow Credentials");
        $this->output("   Current: " . ($currentConfig['credentials'] ? 'true' : 'false'));
        $credentialsInput = $this->prompt("   Allow credentials (true/false)");
        
        if (!empty($credentialsInput)) {
            $config['credentials'] = strtolower($credentialsInput) === 'true';
        }

        // Configure Max Age
        $this->output("\n5. ⏱️ Preflight Max Age");
        $this->output("   Current: " . $currentConfig['max_age'] . " seconds");
        $maxAgeInput = $this->prompt("   Enter max age in seconds (e.g., 86400 for 24 hours)");
        
        if (!empty($maxAgeInput) && is_numeric($maxAgeInput)) {
            $config['max_age'] = (int)$maxAgeInput;
        }

        return $config;
    }

    private function saveCorsConfig(string $configPath, array $config): void
    {
        $configContent = "<?php\n\n";
        $configContent .= "/**\n";
        $configContent .= " * CORS Configuration for FlexiAPI\n";
        $configContent .= " * Generated on: " . date('Y-m-d H:i:s') . "\n";
        $configContent .= " */\n\n";
        $configContent .= "return [\n";
        $configContent .= "    'origins' => " . $this->arrayToPhp($config['origins']) . ",\n";
        $configContent .= "    'methods' => " . $this->arrayToPhp($config['methods']) . ",\n";
        $configContent .= "    'headers' => " . $this->arrayToPhp($config['headers']) . ",\n";
        $configContent .= "    'credentials' => " . ($config['credentials'] ? 'true' : 'false') . ",\n";
        $configContent .= "    'max_age' => " . $config['max_age'] . "\n";
        $configContent .= "];\n";

        file_put_contents($configPath, $configContent);
    }

    private function updateIndexPhp(array $corsConfig): void
    {
        $indexPath = $this->getProjectRoot() . '/public/index.php';
        
        if (!file_exists($indexPath)) {
            $this->output("❌ Warning: index.php not found at: " . $indexPath);
            return;
        }

        $content = file_get_contents($indexPath);
        
        // Create new CORS headers section
        $corsSection = "// Load CORS configuration\n";
        $corsSection .= "\$corsConfigPath = __DIR__ . '/../config/cors.php';\n";
        $corsSection .= "if (file_exists(\$corsConfigPath)) {\n";
        $corsSection .= "    \$corsConfig = require \$corsConfigPath;\n";
        $corsSection .= "    \n";
        $corsSection .= "    // Set CORS headers\n";
        $corsSection .= "    header('Access-Control-Allow-Origin: ' . implode(', ', \$corsConfig['origins']));\n";
        $corsSection .= "    header('Access-Control-Allow-Methods: ' . implode(', ', \$corsConfig['methods']));\n";
        $corsSection .= "    header('Access-Control-Allow-Headers: ' . implode(', ', \$corsConfig['headers']));\n";
        $corsSection .= "    header('Access-Control-Allow-Credentials: ' . (\$corsConfig['credentials'] ? 'true' : 'false'));\n";
        $corsSection .= "    header('Access-Control-Max-Age: ' . \$corsConfig['max_age']);\n";
        $corsSection .= "} else {\n";
        $corsSection .= "    // Fallback CORS headers\n";
        $corsSection .= "    header('Access-Control-Allow-Origin: *');\n";
        $corsSection .= "    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');\n";
        $corsSection .= "    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, Auth-x');\n";
        $corsSection .= "}\n";

        // Replace existing CORS section
        $pattern = '/\/\/ Handle CORS.*?header\([\'"]Access-Control-Allow-Headers[\'"].*?\);/s';
        $replacement = "// Handle CORS\n" . $corsSection;
        
        $newContent = preg_replace($pattern, $replacement, $content);
        
        // If pattern didn't match, try to find and replace line by line
        if ($newContent === $content) {
            $lines = explode("\n", $content);
            $newLines = [];
            $skipNext = 0;
            
            foreach ($lines as $i => $line) {
                if ($skipNext > 0) {
                    $skipNext--;
                    continue;
                }
                
                if (strpos($line, '// Handle CORS') !== false) {
                    $newLines[] = "// Handle CORS";
                    $newLines[] = $corsSection;
                    // Skip the next 3 lines (original CORS headers)
                    $skipNext = 3;
                } else {
                    $newLines[] = $line;
                }
            }
            $newContent = implode("\n", $newLines);
        }
        
        file_put_contents($indexPath, $newContent);
    }

    private function displayFinalConfig(array $config): void
    {
        $this->output("  🌍 Origins: " . implode(', ', $config['origins']));
        $this->output("  🔧 Methods: " . implode(', ', $config['methods']));
        $this->output("  📋 Headers: " . implode(', ', $config['headers']));
        $this->output("  🔐 Credentials: " . ($config['credentials'] ? 'Allowed' : 'Not allowed'));
        $this->output("  ⏱️ Max Age: " . $config['max_age'] . " seconds");
    }

    private function arrayToPhp(array $array): string
    {
        $elements = array_map(function($item) {
            return "'" . addslashes($item) . "'";
        }, $array);
        
        return "[\n        " . implode(",\n        ", $elements) . "\n    ]";
    }

    private function prompt(string $message): string
    {
        echo $message . ": ";
        return trim(fgets(STDIN));
    }

    private function getProjectRoot(): string
    {
        // Check if we're in a vendor directory (Composer installation)
        $currentDir = dirname(__DIR__, 3); // Go to package root
        
        if (strpos($currentDir, 'vendor' . DIRECTORY_SEPARATOR) !== false) {
            // We're installed via Composer, find the project root
            $parts = explode(DIRECTORY_SEPARATOR, $currentDir);
            $vendorIndex = array_search('vendor', $parts);
            if ($vendorIndex !== false) {
                // Project root is one level up from vendor
                return implode(DIRECTORY_SEPARATOR, array_slice($parts, 0, $vendorIndex));
            }
        }
        
        // We're in development mode, use current working directory
        return getcwd() ?: $currentDir;
    }
}