<?php

namespace FlexiAPI\CLI\Commands;

class InitCommand extends BaseCommand
{
    protected string $signature = 'init';
    protected string $description = 'Initialize a new FlexiAPI project in current directory';
    
    public function execute(array $args): int
    {
        $this->handle();
        return 0;
    }
    
    public function handle(): void
    {
        $this->output("\n🚀 Initializing FlexiAPI Project", 'header');
        $this->output("");
        
        // Check if directory is empty or confirm overwrite
        if ($this->directoryHasFiles()) {
            $continue = $this->confirm("Directory is not empty. Continue initialization?");
            if (!$continue) {
                $this->output("Initialization cancelled.", 'yellow');
                return;
            }
        }
        
        // Create project structure
        $this->createProjectStructure();
        
        // Copy essential files
        $this->copyFrameworkFiles();
        
        // Create composer.json for the project
        $this->createProjectComposerJson();
        
        // Create deployment files
        $this->createDeploymentFiles();
        
        // Install dependencies
        $this->installDependencies();
        
        $this->output("\n✅ FlexiAPI project initialized successfully!", 'green');
        $this->showNextSteps();
    }
    
    private function directoryHasFiles(): bool
    {
        $files = glob('*');
        // Ignore common files that are safe to overwrite
        $ignored = ['.', '..', '.git', '.gitignore', 'README.md'];
        
        foreach ($files as $file) {
            if (!in_array($file, $ignored)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function createProjectStructure(): void
    {
        $directories = [
            'bin',
            'config', 
            'endpoints',
            'exports',
            'postman',
            'public',
            'src',
            'storage',
            'storage/logs',
            'storage/cache',
            'storage/uploads'
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        
        // Create .gitkeep files
        $keepDirs = ['endpoints', 'exports', 'storage/logs', 'storage/cache'];
        foreach ($keepDirs as $dir) {
            file_put_contents($dir . '/.gitkeep', '');
        }
        
        $this->output("📁 Created project directory structure", 'green');
    }
    
    private function copyFrameworkFiles(): void
    {
        // Find FlexiAPI installation directory
        $flexiapiDir = $this->findFlexiApiInstallation();
        
        if (!$flexiapiDir) {
            $this->output("❌ Could not find FlexiAPI installation", 'error');
            return;
        }
        
        // Copy essential files
        $filesToCopy = [
            'bin/flexiapi' => 'bin/flexiapi',
            'public/index.php' => 'public/index.php', 
            'src' => 'vendor/uptura-official/flexiapi/src',
            '.gitignore' => '.gitignore',
            'README.md' => 'README.md'
        ];
        
        foreach ($filesToCopy as $source => $dest) {
            $sourcePath = $flexiapiDir . DIRECTORY_SEPARATOR . $source;
            
            if ($dest === 'src') {
                // For src, we'll rely on composer autoload
                continue;
            }
            
            if (file_exists($sourcePath)) {
                if (is_dir($sourcePath)) {
                    $this->copyDirectory($sourcePath, $dest);
                } else {
                    copy($sourcePath, $dest);
                }
            }
        }
        
        $this->output("📄 Copied framework files", 'green');
    }
    
    private function findFlexiApiInstallation(): ?string
    {
        // Check if running from global installation
        $globalComposerHome = getenv('COMPOSER_HOME') ?: (getenv('HOME') . '/.composer');
        $globalVendor = $globalComposerHome . '/vendor/uptura-official/flexiapi';
        
        if (is_dir($globalVendor)) {
            return $globalVendor;
        }
        
        // Check common global paths
        $globalPaths = [
            $_SERVER['HOME'] . '/.composer/vendor/uptura-official/flexiapi',
            $_SERVER['USERPROFILE'] . '/AppData/Roaming/Composer/vendor/uptura-official/flexiapi'
        ];
        
        foreach ($globalPaths as $path) {
            if (is_dir($path)) {
                return $path;
            }
        }
        
        return null;
    }
    
    private function copyDirectory(string $source, string $dest): void
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($files as $file) {
            $destPath = $dest . DIRECTORY_SEPARATOR . $files->getSubPathName();
            
            if ($file->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                copy($file->getRealPath(), $destPath);
            }
        }
    }
    
    private function createProjectComposerJson(): void
    {
        $projectName = basename(getcwd());
        
        $composerJson = [
            'name' => "project/{$projectName}",
            'description' => "FlexiAPI project: {$projectName}",
            'type' => 'project',
            'require' => [
                'php' => '>=8.0',
                'ext-pdo' => '*',
                'ext-pdo_mysql' => '*',
                'ext-openssl' => '*',
                'ext-json' => '*'
            ],
            'autoload' => [
                'psr-4' => [
                    'FlexiAPI\\' => 'src/',
                    'FlexiAPI\\Endpoints\\' => 'endpoints/'
                ]
            ],
            'scripts' => [
                'flexiapi' => 'bin/flexiapi'
            ]
        ];
        
        file_put_contents('composer.json', json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->output("📦 Created project composer.json", 'green');
    }
    
    private function createDeploymentFiles(): void
    {
        // Create Procfile for PaaS deployments (Heroku, Railway, etc.)
        $procfile = <<<'PROCFILE'
web: php -S 0.0.0.0:$PORT -t public
PROCFILE;
        
        file_put_contents('Procfile', $procfile);
        
        // Create .nixpacks.toml for Railway and other Nixpacks platforms
        $nixpacks = <<<'NIXPACKS'
[start]
cmd = "php -S 0.0.0.0:8080 -t /app/public"
NIXPACKS;
        
        file_put_contents('.nixpacks.toml', $nixpacks);
        
        // Create .platform.app.yaml for Platform.sh
        $platformConfig = <<<'YAML'
name: flexiapi
type: php:8.0

web:
    locations:
        "/":
            root: "public"
            passthru: "/index.php"

disk: 2048

mounts:
    "/storage/logs":
        source: local
        source_path: "logs"
    "/storage/cache":
        source: local
        source_path: "cache"

build:
    flavor: composer

hooks:
    build: |
        set -e
    deploy: |
        php bin/flexiapi setup --non-interactive
YAML;
        
        if (!is_dir('.platform')) {
            mkdir('.platform', 0755, true);
        }
        file_put_contents('.platform.app.yaml', $platformConfig);
        
        // Create app.json for Heroku Button
        $herokuConfig = [
            'name' => 'FlexiAPI Project',
            'description' => 'A FlexiAPI REST API project ready for deployment',
            'repository' => 'https://github.com/your-username/your-project',
            'keywords' => ['php', 'api', 'rest', 'flexiapi'],
            'env' => [
                'APP_KEY' => [
                    'description' => 'Application encryption key',
                    'generator' => 'secret'
                ],
                'DB_HOST' => [
                    'description' => 'Database host',
                    'value' => 'localhost'
                ],
                'DB_DATABASE' => [
                    'description' => 'Database name'
                ],
                'DB_USERNAME' => [
                    'description' => 'Database username'
                ],
                'DB_PASSWORD' => [
                    'description' => 'Database password'
                ]
            ],
            'buildpacks' => [
                [
                    'url' => 'heroku/php'
                ]
            ]
        ];
        
        file_put_contents('app.json', json_encode($herokuConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        $this->output("🚀 Created deployment configuration files", 'green');
    }
    
    private function installDependencies(): void
    {
        $this->output("📥 Installing dependencies...", 'info');
        
        $output = [];
        $returnCode = 0;
        
        exec('composer install --no-dev --optimize-autoloader 2>&1', $output, $returnCode);
        
        if ($returnCode === 0) {
            $this->output("✅ Dependencies installed successfully", 'green');
        } else {
            $this->output("⚠️ Dependencies installation had issues:", 'yellow');
            foreach ($output as $line) {
                $this->output("   " . $line);
            }
        }
    }
    
    private function showNextSteps(): void
    {
        $this->output("\n🎯 Next Steps:", 'blue');
        $this->output("1. Configure your API:");
        $this->output("   php bin/flexiapi setup", 'cyan');
        $this->output("");
        $this->output("2. Create your first endpoint:");
        $this->output("   php bin/flexiapi create users", 'cyan');
        $this->output("");
        $this->output("3. Start development server:");
        $this->output("   php bin/flexiapi serve", 'cyan');
        $this->output("");
        $this->output("📚 Check README.md for detailed documentation!");
    }
}