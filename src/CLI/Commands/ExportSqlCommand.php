<?php

namespace FlexiAPI\CLI\Commands;

class ExportSqlCommand extends BaseCommand
{
    protected string $signature = 'export:sql';
    protected string $description = 'Export unified SQL file with all table definitions';
    
    public function execute(array $args): int
    {
        $this->handle($args);
        return 0;
    }
    
    public function handle(array $args): void
    {
        $this->output("📤 Exporting SQL Schema", 'header');
        $this->output("");
        
        // Get export options
        $includeData = $this->confirm("Include sample data in export?", false);
        $includeDrop = $this->confirm("Include DROP TABLE statements?", false);
        $includeAuth = $this->confirm("Include authentication tables?", true);
        
        // Discover endpoints
        $endpoints = $this->discoverEndpoints();
        
        if (empty($endpoints) && !$includeAuth) {
            $this->output("❌ No endpoints found and auth tables not included!", 'error');
            $this->output("   Use: php bin/flexiapi create:endpoint <name>", 'info');
            return;
        }
        
        $this->output("📊 Processing " . count($endpoints) . " endpoint(s):", 'info');
        foreach ($endpoints as $endpoint) {
            $this->output("   • {$endpoint}", 'cyan');
        }
        $this->output("");
        
        // Generate unified SQL
        $sql = $this->generateUnifiedSQL($endpoints, $includeData, $includeDrop, $includeAuth);
        
        // Save SQL file
        $outputFile = $this->saveSQLFile($sql);
        
        $this->output("✅ SQL export completed successfully!", 'green');
        $this->output("📁 Saved to: {$outputFile}", 'info');
        $this->output("");
        $this->showUsageInstructions($outputFile);
    }
    
    private function discoverEndpoints(): array
    {
        $endpoints = [];
        
        if (!is_dir('sql')) {
            return $endpoints;
        }
        
        $sqlFiles = glob('sql/*.sql');
        
        foreach ($sqlFiles as $sqlFile) {
            $name = basename($sqlFile, '.sql');
            $endpoints[] = $name;
        }
        
        return $endpoints;
    }
    
    private function generateUnifiedSQL(array $endpoints, bool $includeData, bool $includeDrop, bool $includeAuth): string
    {
        $sql = [];
        
        // Add header
        $sql[] = $this->generateSQLHeader();
        
        // Add authentication tables if requested
        if ($includeAuth) {
            $sql[] = $this->generateAuthTables($includeDrop);
        }
        
        // Add endpoint tables
        foreach ($endpoints as $endpoint) {
            $sql[] = $this->generateEndpointSQL($endpoint, $includeDrop);
            
            if ($includeData) {
                $sql[] = $this->generateSampleData($endpoint);
            }
        }
        
        // Add footer
        $sql[] = $this->generateSQLFooter();
        
        return implode("\n\n", array_filter($sql));
    }
    
    private function generateSQLHeader(): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $config = $this->loadConfig();
        $dbName = $config['database']['database'] ?? 'flexiapi_db';
        
        return <<<SQL
-- ================================================================
-- FlexiAPI - Unified Database Schema Export
-- Generated on: {$timestamp}
-- Database: {$dbName}
-- ================================================================

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Set SQL mode
SET sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION';

-- Set character set
SET NAMES utf8mb4;
SET character_set_client = utf8mb4;
SQL;
    }
    
    private function generateAuthTables(bool $includeDrop): string
    {
        $sql = [];
        
        $sql[] = "-- ================================================================";
        $sql[] = "-- Authentication Tables";
        $sql[] = "-- ================================================================";
        
        if ($includeDrop) {
            $sql[] = "DROP TABLE IF EXISTS `api_keys`;";
            $sql[] = "DROP TABLE IF EXISTS `users`;";
        }
        
        // Users table
        $sql[] = <<<SQL
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `is_active` boolean DEFAULT TRUE,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_email` (`email`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        
        // API Keys table
        $sql[] = <<<SQL
CREATE TABLE IF NOT EXISTS `api_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `api_key` varchar(255) NOT NULL UNIQUE,
  `is_active` boolean DEFAULT TRUE,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_api_key` (`api_key`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_active` (`is_active`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        
        // Add default admin user
        $sql[] = <<<SQL
-- Default admin user (password: admin123)
INSERT IGNORE INTO `users` (`id`, `email`, `password`, `name`, `is_active`) VALUES
(1, 'admin@flexiapi.com', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'FlexiAPI Admin', 1);
SQL;
        
        return implode("\n", $sql);
    }
    
    private function generateEndpointSQL(string $endpoint, bool $includeDrop): string
    {
        $sqlFile = "sql/{$endpoint}.sql";
        
        if (!file_exists($sqlFile)) {
            return "-- Warning: SQL file for '{$endpoint}' not found!";
        }
        
        $sql = [];
        $sql[] = "-- ================================================================";
        $sql[] = "-- Table: {$endpoint}";
        $sql[] = "-- ================================================================";
        
        if ($includeDrop) {
            $sql[] = "DROP TABLE IF EXISTS `{$endpoint}`;";
        }
        
        // Read and clean the SQL file content
        $content = file_get_contents($sqlFile);
        
        // Remove comments and clean up
        $lines = explode("\n", $content);
        $cleanLines = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and comments at the beginning
            if (empty($line) || str_starts_with($line, '--')) {
                continue;
            }
            
            $cleanLines[] = $line;
        }
        
        $sql[] = implode("\n", $cleanLines);
        
        return implode("\n", $sql);
    }
    
    private function generateSampleData(string $endpoint): string
    {
        $columns = $this->getEndpointColumns($endpoint);
        
        if (empty($columns)) {
            return "-- No sample data available for '{$endpoint}'";
        }
        
        $sql = [];
        $sql[] = "-- Sample data for {$endpoint}";
        
        // Generate 3-5 sample records
        $sampleCount = rand(3, 5);
        $values = [];
        
        for ($i = 1; $i <= $sampleCount; $i++) {
            $rowValues = [];
            
            foreach ($columns as $column) {
                $value = $this->generateSampleValue($column['name'], $column['type'], $i);
                $rowValues[] = $this->formatSQLValue($value, $column['type']);
            }
            
            $values[] = "(" . implode(', ', $rowValues) . ")";
        }
        
        $columnNames = array_column($columns, 'name');
        $sql[] = "INSERT IGNORE INTO `{$endpoint}` (`" . implode('`, `', $columnNames) . "`) VALUES";
        $sql[] = implode(",\n", $values) . ";";
        
        return implode("\n", $sql);
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
    
    private function generateSampleValue(string $columnName, string $columnType, int $index): mixed
    {
        $columnName = strtolower($columnName);
        $columnType = strtoupper($columnType);
        
        // Based on column name patterns
        if (str_contains($columnName, 'email')) {
            return "user{$index}@example.com";
        }
        if (str_contains($columnName, 'name')) {
            $names = ['John Doe', 'Jane Smith', 'Mike Johnson', 'Sarah Wilson', 'David Brown'];
            return $names[($index - 1) % count($names)];
        }
        if (str_contains($columnName, 'title')) {
            return "Sample Title {$index}";
        }
        if (str_contains($columnName, 'description')) {
            return "This is a sample description for record {$index}";
        }
        if (str_contains($columnName, 'price')) {
            return round(rand(10, 1000) + (rand(0, 99) / 100), 2);
        }
        if (str_contains($columnName, 'quantity') || str_contains($columnName, 'count')) {
            return rand(1, 100);
        }
        if (str_contains($columnName, 'status')) {
            $statuses = ['active', 'inactive', 'pending', 'completed'];
            return $statuses[($index - 1) % count($statuses)];
        }
        if (str_contains($columnName, 'url') || str_contains($columnName, 'website')) {
            return "https://example{$index}.com";
        }
        if (str_contains($columnName, 'phone')) {
            return "+1-555-000-" . str_pad($index, 4, '0', STR_PAD_LEFT);
        }
        if (str_contains($columnName, 'address')) {
            return "{$index} Sample Street, Sample City, SC 12345";
        }
        
        // Based on column type
        if (str_contains($columnType, 'VARCHAR') || str_contains($columnType, 'TEXT')) {
            return "Sample value {$index}";
        }
        if (str_contains($columnType, 'INT')) {
            return rand(1, 1000);
        }
        if (str_contains($columnType, 'DECIMAL') || str_contains($columnType, 'FLOAT')) {
            return round(rand(1, 1000) + (rand(0, 99) / 100), 2);
        }
        if (str_contains($columnType, 'BOOLEAN') || str_contains($columnType, 'BOOL')) {
            return rand(0, 1) === 1;
        }
        if (str_contains($columnType, 'DATETIME') || str_contains($columnType, 'TIMESTAMP')) {
            return date('Y-m-d H:i:s', strtotime("-{$index} days"));
        }
        if (str_contains($columnType, 'JSON')) {
            return json_encode(['key' => "value{$index}", 'data' => ['item' => $index]]);
        }
        
        return "sample_{$index}";
    }
    
    private function formatSQLValue(mixed $value, string $columnType): string
    {
        if ($value === null) {
            return 'NULL';
        }
        
        $columnType = strtoupper($columnType);
        
        if (str_contains($columnType, 'BOOLEAN') || str_contains($columnType, 'BOOL')) {
            return $value ? '1' : '0';
        }
        
        if (str_contains($columnType, 'INT') || str_contains($columnType, 'DECIMAL') || str_contains($columnType, 'FLOAT')) {
            return (string)$value;
        }
        
        // String values - escape and quote
        $escaped = str_replace("'", "''", $value);
        return "'{$escaped}'";
    }
    
    private function generateSQLFooter(): string
    {
        return <<<SQL
-- ================================================================
-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ================================================================
-- FlexiAPI Schema Export Complete
-- ================================================================
SQL;
    }
    
    private function saveSQLFile(string $sql): string
    {
        // Create exports directory if it doesn't exist
        if (!is_dir('exports')) {
            mkdir('exports', 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "FlexiAPI_Schema_{$timestamp}.sql";
        $filepath = "exports/{$filename}";
        
        // Save SQL file
        file_put_contents($filepath, $sql);
        
        // Also save as latest
        file_put_contents('exports/FlexiAPI_Schema_Latest.sql', $sql);
        
        return $filepath;
    }
    
    private function showUsageInstructions(string $filepath): void
    {
        $config = $this->loadConfig();
        $dbName = $config['database']['database'] ?? 'flexiapi_db';
        $dbUser = $config['database']['username'] ?? 'username';
        $dbHost = $config['database']['host'] ?? 'localhost';
        
        $this->output("📋 Usage Instructions:", 'blue');
        $this->output("");
        $this->output("💾 Import to MySQL:", 'blue');
        $this->output("mysql -u {$dbUser} -p {$dbName} < {$filepath}");
        $this->output("");
        $this->output("🐳 Import to Docker MySQL:", 'blue');
        $this->output("docker exec -i mysql_container mysql -u {$dbUser} -p{$dbName} < {$filepath}");
        $this->output("");
        $this->output("🔧 Create Database (if needed):", 'blue');
        $this->output("mysql -u {$dbUser} -p -e \"CREATE DATABASE IF NOT EXISTS {$dbName};\"");
        $this->output("");
        $this->output("📊 File Details:", 'info');
        $this->output("   Size: " . $this->formatFileSize(filesize($filepath)));
        $this->output("   Lines: " . count(file($filepath)));
        $this->output("   Encoding: UTF-8");
        $this->output("");
    }
    
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    protected function loadConfig(): array
    {
        $configFile = 'config/config.php';
        if (file_exists($configFile)) {
            return require $configFile;
        }
        
        return [
            'database' => [
                'host' => 'localhost',
                'database' => 'flexiapi_db',
                'username' => 'username'
            ]
        ];
    }
}