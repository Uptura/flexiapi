<?php

namespace FlexiAPI\CLI;

use FlexiAPI\CLI\Commands\CreateEndpointCommand;
use FlexiAPI\CLI\Commands\UpdateEndpointCommand;
use FlexiAPI\CLI\Commands\SetupCommand;
use FlexiAPI\CLI\Commands\InitCommand;
use FlexiAPI\CLI\Commands\GeneratePostmanCommand;
use FlexiAPI\CLI\Commands\ExportSqlCommand;
use FlexiAPI\CLI\Commands\ServeCommand;
use FlexiAPI\CLI\Commands\ListEndpointsCommand;
use FlexiAPI\CLI\Commands\ConfigureCorsCommand;

class Console
{
    private array $commands = [];
    private string $commandPrefix;
    
    public function __construct()
    {
        $this->registerCommands();
        $this->commandPrefix = $this->detectCommandPrefix();
    }
    
    private function detectCommandPrefix(): string
    {
        // Check if running as global Composer package
        global $argv;
        $scriptPath = $argv[0] ?? '';
        
        // If script contains 'flexiapi' without 'bin/', it's likely global Composer
        if (strpos($scriptPath, 'flexiapi') !== false && strpos($scriptPath, 'bin/') === false) {
            return 'flexiapi';
        }
        
        // Check if vendor/bin/flexiapi exists (local Composer install)
        if (file_exists('vendor/bin/flexiapi') || file_exists('../vendor/bin/flexiapi')) {
            return 'vendor/bin/flexiapi';
        }
        
        // Default to development mode
        return 'php bin/flexiapi';
    }
    
    private function registerCommands(): void
    {
        $this->commands = [
            // Full command names
            'setup' => SetupCommand::class,
            'init' => InitCommand::class,
            'create:endpoint' => CreateEndpointCommand::class,
            'update:endpoint' => UpdateEndpointCommand::class,
            'list:endpoints' => ListEndpointsCommand::class,
            'generate:postman' => GeneratePostmanCommand::class,
            'export:sql' => ExportSqlCommand::class,
            'serve' => ServeCommand::class,
            'configure:cors' => ConfigureCorsCommand::class,
            
            // Aliases
            'create' => CreateEndpointCommand::class,
            'new' => CreateEndpointCommand::class,
            'update' => UpdateEndpointCommand::class,
            'edit' => UpdateEndpointCommand::class,
            'list' => ListEndpointsCommand::class,
            'ls' => ListEndpointsCommand::class,
            'postman' => GeneratePostmanCommand::class,
            'pm' => GeneratePostmanCommand::class,
            'export' => ExportSqlCommand::class,
            'sql' => ExportSqlCommand::class,
            'server' => ServeCommand::class,
            'start' => ServeCommand::class,
            'cors' => ConfigureCorsCommand::class,
            'config:cors' => ConfigureCorsCommand::class,
            
            // Special cases
            'help' => null,
            'version' => null,
        ];
    }
    
    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'help';
        $args = array_slice($argv, 2);
        
        // Handle help command
        if ($command === 'help' || $command === '--help' || $command === '-h') {
            $this->showHelp();
            return 0;
        }
        
        // Handle version command
        if ($command === 'version' || $command === '--version' || $command === '-v') {
            $this->showVersion();
            return 0;
        }
        
        // Check if command exists
        if (!isset($this->commands[$command])) {
            $this->output("❌ Unknown command: {$command}", 'error');
            
            // Suggest similar commands
            $suggestions = $this->findSimilarCommands($command);
            if (!empty($suggestions)) {
                $this->output("\n💡 Did you mean:", 'yellow');
                foreach ($suggestions as $suggestion) {
                    $this->output("   flexiapi {$suggestion}", 'cyan');
                }
            }
            
            $this->output("\n📋 Run 'flexiapi help' for all available commands.", 'info');
            return 1;
        }
        
        try {
            $commandClass = $this->commands[$command];
            $commandInstance = new $commandClass();
            return $commandInstance->execute($args);
        } catch (\Exception $e) {
            $this->output("Error executing command: " . $e->getMessage(), 'error');
            return 1;
        }
    }
    
    private function showHelp(): void
    {
        $this->output(
            "   _____ _           _          _____ _____ \n" .
            "  |  ___| | _____  _(_)   /\\   |  __ \\_   _|\n" .
            "  | |_  | |/ _ \\ \\/ /| |  /  \\  | |__) || |  \n" .
            "  |  _| | |  __/>  < | | / /\\ \\ |  ___/ | |  \n" .
            "  | |   | |\\___/_/\\_\\|_|/_/  \\_\\|_|    |___|\n" .
            "  |_|   |_|                               \n" .
            "                                          \n" .
            "FlexiAPI CLI v3.3.0 - Rapid API Development Framework\n",
            'info'
        );
        // Determine installed FlexiAPI version dynamically from Composer
        $version = null;
        if (class_exists('Composer\\InstalledVersions')) {
            try {
                $version = \Composer\InstalledVersions::getPrettyVersion('uptura-official/flexiapi');
            } catch (\Throwable $e) {
                $version = null;
            }
        }
        if (!$version) {
            // Fallback to local composer.json extra if running from source
            $composerPath = __DIR__ . '/../../composer.json';
            if (file_exists($composerPath)) {
                $composerData = json_decode(file_get_contents($composerPath), true);
                $version = $composerData['extra']['flexiapi']['framework-version'] ?? 'unknown';
            } else {
                $version = 'unknown';
            }
        }
        $banner = "\nFlexiAPI CLI v{$version} - Rapid API Development Framework\n";
    $this->output($banner, 'info');

        $this->output("USAGE:", 'header');
        $this->output("  {$this->commandPrefix} <command> [options] [arguments]");
        $this->output("");
        
        $this->output("🚀 QUICK START:", 'header');
        $this->output("  1. {$this->commandPrefix} setup              # Configure your API");
        $this->output("  2. {$this->commandPrefix} create users       # Create first endpoint");
        $this->output("  3. {$this->commandPrefix} serve              # Start development server");
        $this->output("  4. {$this->commandPrefix} postman            # Generate Postman collection");
        $this->output("");
        
        $this->output("📝 ENDPOINT MANAGEMENT:", 'header');
        $commands = [
            'create:endpoint <name>' => 'Create new API endpoint with database table',
            'update:endpoint <name>' => 'Modify existing endpoint (add/remove columns)',
            'list:endpoints [options]' => 'List all created endpoints with details',
        ];
        
        foreach ($commands as $cmd => $description) {
            $this->output(sprintf("  %-25s %s", $cmd, $description), 'cyan');
        }
        $this->output("");
        
        $this->output("⚙️  CONFIGURATION & SETUP:", 'header');
        $configCommands = [
            'setup' => 'Interactive setup (database, auth, rate limiting)',
            'configure:cors' => 'Configure CORS (Cross-Origin Resource Sharing) policy',
            'init' => 'Alias for setup command',
        ];
        
        foreach ($configCommands as $cmd => $description) {
            $this->output(sprintf("  %-25s %s", $cmd, $description), 'cyan');
        }
        $this->output("");
        
        $this->output("📤 EXPORT & GENERATION:", 'header');
        $exportCommands = [
            'generate:postman' => 'Generate Postman collection for all endpoints',
            'export:sql' => 'Export unified SQL file with all schemas',
        ];
        
        foreach ($exportCommands as $cmd => $description) {
            $this->output(sprintf("  %-25s %s", $cmd, $description), 'cyan');
        }
        $this->output("");
        
        $this->output("🖥️  DEVELOPMENT SERVER:", 'header');
        $this->output("  serve [--host] [--port]    Start development server");
        $this->output("    --host=127.0.0.1         Server host (default: 127.0.0.1)");
        $this->output("    --port=8000              Server port (default: 8000)");
        $this->output("    --verbose, -v            Enable verbose logging");
        $this->output("");
        
        $this->output("🔗 COMMAND ALIASES:", 'header');
        $aliases = [
            'create, new' => 'create:endpoint',
            'update, edit' => 'update:endpoint',
            'list, ls' => 'list:endpoints',
            'postman, pm' => 'generate:postman',
            'export, sql' => 'export:sql',
            'server, start' => 'serve',
            'cors, config:cors' => 'configure:cors',
        ];
        
        foreach ($aliases as $alias => $original) {
            $this->output(sprintf("  %-25s → %s", $alias, $original), 'yellow');
        }
        $this->output("");
        
        $this->output("📋 EXAMPLES:", 'header');
        $this->output("  {$this->commandPrefix} create users          # Create users endpoint");
        $this->output("  {$this->commandPrefix} update users          # Modify users endpoint");
        $this->output("  {$this->commandPrefix} list --details        # Show detailed endpoint info");
        $this->output("  {$this->commandPrefix} serve --port=9000     # Start server on port 9000");
        $this->output("  {$this->commandPrefix} export --data         # Export SQL with sample data");
        $this->output("");
        
        $this->output("ℹ️  MORE INFO:", 'header');
        $this->output("  {$this->commandPrefix} <command> --help      # Get help for specific command");
        $this->output("  {$this->commandPrefix} version               # Show version information");
        $this->output("  Documentation: https://github.com/flexiapi/framework");
        $this->output("");
    }
    
    private function findSimilarCommands(string $command): array
    {
        $suggestions = [];
        $allCommands = array_keys($this->commands);
        
        foreach ($allCommands as $availableCommand) {
            // Skip null commands (help, version)
            if ($this->commands[$availableCommand] === null) {
                continue;
            }
            
            // Calculate similarity
            $similarity = 0;
            similar_text($command, $availableCommand, $similarity);
            
            // Suggest if similarity is above 60% or contains partial match
            if ($similarity > 60 || str_contains($availableCommand, $command) || str_contains($command, $availableCommand)) {
                $suggestions[] = $availableCommand;
            }
        }
        
        // Remove duplicates and limit to 3 suggestions
        return array_slice(array_unique($suggestions), 0, 3);
    }
    
    private function showVersion(): void
    {
        $this->output(
            "   _____ _           _          _____ _____ \n" .
            "  |  ___| | _____  _(_)   /\\   |  __ \\_   _|\n" .
            "  | |_  | |/ _ \\ \\/ /| |  /  \\  | |__) || |  \n" .
            "  |  _| | |  __/>  < | | / /\\ \\ |  ___/ | |  \n" .
            "  | |   | |\\___/_/\\_\\|_|/_/  \\_\\|_|    |___|\n" .
            "  |_|   |_|                               \n" .
            "                                          \n" .
            "FlexiAPI CLI Framework v3.3.0\n",
            'info'
        );
        // Determine installed version dynamically for the system info banner as well
        $version = null;
        if (class_exists('Composer\\InstalledVersions')) {
            try {
                $version = \Composer\InstalledVersions::getPrettyVersion('uptura-official/flexiapi');
            } catch (\Throwable $e) {
                $version = null;
            }
        }
        if (!$version) {
            $composerPath = __DIR__ . '/../../composer.json';
            if (file_exists($composerPath)) {
                $composerData = json_decode(file_get_contents($composerPath), true);
                $version = $composerData['extra']['flexiapi']['framework-version'] ?? 'unknown';
            } else {
                $version = 'unknown';
            }
        }
        $banner = "\nFlexiAPI CLI Framework v{$version}\n";
        $this->output($banner, 'info');
        
        $this->output("📋 System Information:", 'header');
        $this->output("  PHP Version: " . phpversion());
        $this->output("  Platform: " . php_uname('s') . ' ' . php_uname('r'));
        $this->output("  Architecture: " . php_uname('m'));
        
        // Check for required extensions
        $this->output("\n🔧 Extension Status:", 'header');
        $requiredExtensions = ['pdo', 'pdo_mysql', 'openssl', 'json'];
        
        foreach ($requiredExtensions as $ext) {
            $status = extension_loaded($ext) ? '✅' : '❌';
            $this->output("  {$status} {$ext}");
        }
        
        $this->output("\n📁 Current Directory: " . getcwd());
        $this->output("💾 Memory Limit: " . ini_get('memory_limit'));
        $this->output("");
    }
    
    public function output(string $message, string $type = 'normal'): void
    {
        $colors = [
            'normal' => '',
            'info' => "\033[36m",      // Cyan
            'success' => "\033[32m",    // Green
            'warning' => "\033[33m",    // Yellow
            'error' => "\033[31m",      // Red
            'header' => "\033[1;34m",   // Bold Blue
            'cyan' => "\033[36m",       // Cyan
            'green' => "\033[32m",      // Green
            'yellow' => "\033[33m",     // Yellow
            'blue' => "\033[34m",       // Blue
            'question' => "\033[1;33m", // Bold Yellow
        ];
        
        $reset = "\033[0m";
        
        // Disable colors on Windows Command Prompt (unless Windows Terminal)
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && !isset($_SERVER['WT_SESSION'])) {
            $color = '';
            $reset = '';
        } else {
            $color = $colors[$type] ?? '';
        }
        
        echo $color . $message . $reset . "\n";
    }
    
    public function input(string $prompt): string
    {
        echo $prompt . " ";
        return trim(fgets(STDIN));
    }
    
    public function confirm(string $message): bool
    {
        $response = $this->input($message . " (y/N):");
        return strtolower($response) === 'y' || strtolower($response) === 'yes';
    }
}