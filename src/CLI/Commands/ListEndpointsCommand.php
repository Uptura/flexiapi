<?php

namespace FlexiAPI\CLI\Commands;

class ListEndpointsCommand extends BaseCommand
{
    protected string $signature = 'list:endpoints';
    protected string $description = 'List all created API endpoints with their details';
    
    public function execute(array $args): int
    {
        $this->handle($args);
        return 0;
    }
    
    public function handle(array $args): void
    {
        $this->output("📋 FlexiAPI Endpoints Overview", 'header');
        $this->output("");
        
        // Parse options
        $showDetails = $this->hasOption($args, '--details') || $this->hasOption($args, '-d');
        $showUrls = $this->hasOption($args, '--urls') || $this->hasOption($args, '-u');
        $format = $this->getOption($args, '--format', 'table'); // table, json, csv
        
        // Discover endpoints
        $endpoints = $this->discoverEndpoints();
        
        if (empty($endpoints)) {
            $this->output("❌ No endpoints found!", 'error');
            $this->output("   Create your first endpoint:", 'info');
            $this->output("   php bin/flexiapi create:endpoint users", 'cyan');
            return;
        }
        
        // Display based on format
        switch ($format) {
            case 'json':
                $this->displayJSON($endpoints);
                break;
            case 'csv':
                $this->displayCSV($endpoints);
                break;
            default:
                $this->displayTable($endpoints, $showDetails, $showUrls);
        }
        
        $this->displaySummary($endpoints);
    }
    
    private function hasOption(array $args, string $option): bool
    {
        return in_array($option, $args);
    }
    
    private function getOption(array $args, string $option, string $default = ''): string
    {
        $key = array_search($option, $args);
        if ($key !== false && isset($args[$key + 1])) {
            return $args[$key + 1];
        }
        return $default;
    }
    
    private function discoverEndpoints(): array
    {
        $endpoints = [];
        
        if (!is_dir('endpoints')) {
            return $endpoints;
        }
        
        $controllers = glob('endpoints/*Controller.php');
        
        foreach ($controllers as $controller) {
            $endpointData = $this->analyzeEndpoint($controller);
            if ($endpointData) {
                $endpoints[] = $endpointData;
            }
        }
        
        // Sort by name
        usort($endpoints, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        
        return $endpoints;
    }
    
    private function analyzeEndpoint(string $controllerFile): ?array
    {
        $name = basename($controllerFile, 'Controller.php');
        $endpointName = strtolower($name);
        
        $endpoint = [
            'name' => $endpointName,
            'class' => ucfirst($name) . 'Controller',
            'controller_file' => $controllerFile,
            'sql_file' => "sql/{$endpointName}.sql",
            'routes_file' => "endpoints/{$endpointName}Routes.php",
            'status' => 'unknown',
            'columns' => [],
            'created_at' => null,
            'file_size' => 0,
            'methods' => ['GET', 'POST', 'PUT', 'DELETE']
        ];
        
        // Check file existence and status
        $endpoint['status'] = $this->getEndpointStatus($endpoint);
        
        // Get file info
        if (file_exists($controllerFile)) {
            $endpoint['created_at'] = date('Y-m-d H:i:s', filemtime($controllerFile));
            $endpoint['file_size'] = filesize($controllerFile);
        }
        
        // Parse columns from SQL file
        $endpoint['columns'] = $this->parseColumns($endpoint['sql_file']);
        
        // Parse fillable fields from controller
        $endpoint['fillable'] = $this->parseFillableFields($controllerFile);
        
        // Get custom methods from controller
        $endpoint['custom_methods'] = $this->parseCustomMethods($controllerFile);
        
        return $endpoint;
    }
    
    private function getEndpointStatus(array $endpoint): string
    {
        $requiredFiles = [
            $endpoint['controller_file'],
            $endpoint['sql_file']
        ];
        
        $existingFiles = 0;
        foreach ($requiredFiles as $file) {
            if (file_exists($file)) {
                $existingFiles++;
            }
        }
        
        if ($existingFiles === count($requiredFiles)) {
            return 'complete';
        } elseif ($existingFiles > 0) {
            return 'partial';
        } else {
            return 'missing';
        }
    }
    
    private function parseColumns(string $sqlFile): array
    {
        if (!file_exists($sqlFile)) {
            return [];
        }
        
        $content = file_get_contents($sqlFile);
        preg_match_all('/`(\w+)` ([^,\n]+)/i', $content, $matches);
        
        $columns = [];
        for ($i = 0; $i < count($matches[1]); $i++) {
            $columnName = $matches[1][$i];
            $columnType = trim($matches[2][$i]);
            
            // Skip system columns for display
            if (in_array($columnName, ['id', 'created_at', 'updated_at'])) {
                continue;
            }
            
            $columns[] = [
                'name' => $columnName,
                'type' => $this->simplifyColumnType($columnType),
                'nullable' => str_contains($columnType, 'NULL') && !str_contains($columnType, 'NOT NULL'),
                'unique' => str_contains($columnType, 'UNIQUE')
            ];
        }
        
        return $columns;
    }
    
    private function simplifyColumnType(string $fullType): string
    {
        $fullType = strtoupper($fullType);
        
        if (str_contains($fullType, 'VARCHAR')) {
            preg_match('/VARCHAR\((\d+)\)/', $fullType, $matches);
            return 'VARCHAR(' . ($matches[1] ?? '255') . ')';
        }
        
        if (str_contains($fullType, 'DECIMAL')) {
            preg_match('/DECIMAL\(([^)]+)\)/', $fullType, $matches);
            return 'DECIMAL(' . ($matches[1] ?? '10,2') . ')';
        }
        
        $types = ['TEXT', 'INT', 'BOOLEAN', 'DATETIME', 'JSON', 'TIMESTAMP'];
        foreach ($types as $type) {
            if (str_contains($fullType, $type)) {
                return $type;
            }
        }
        
        return 'UNKNOWN';
    }
    
    private function parseFillableFields(string $controllerFile): array
    {
        if (!file_exists($controllerFile)) {
            return [];
        }
        
        $content = file_get_contents($controllerFile);
        
        // Extract fillable array
        if (preg_match('/protected\s+array\s+\$fillable\s*=\s*\[(.*?)\];/s', $content, $matches)) {
            $fillableStr = $matches[1];
            preg_match_all('/[\'"]([^\'"]+)[\'"]/', $fillableStr, $fieldMatches);
            return $fieldMatches[1];
        }
        
        return [];
    }
    
    private function parseCustomMethods(string $controllerFile): array
    {
        if (!file_exists($controllerFile)) {
            return [];
        }
        
        $content = file_get_contents($controllerFile);
        
        // Find public methods that are not standard CRUD methods
        preg_match_all('/public\s+function\s+(\w+)\s*\(/', $content, $matches);
        
        $standardMethods = ['index', 'store', 'show', 'update', 'destroy', 'getValidationRules'];
        $customMethods = [];
        
        foreach ($matches[1] as $method) {
            if (!in_array($method, $standardMethods)) {
                $customMethods[] = $method;
            }
        }
        
        return $customMethods;
    }
    
    private function displayTable(array $endpoints, bool $showDetails, bool $showUrls): void
    {
        $config = $this->loadConfig();
        $baseUrl = $config['api']['base_url'] ?? 'http://localhost:8000/api';
        $version = $config['api']['version'] ?? 'v1';
        
        // Header
        $this->output("┌─────────────┬─────────┬─────────┬─────────────┬─────────────────┐", 'cyan');
        $this->output("│ Endpoint    │ Status  │ Columns │ Created     │ Size            │", 'cyan');
        $this->output("├─────────────┼─────────┼─────────┼─────────────┼─────────────────┤", 'cyan');
        
        foreach ($endpoints as $endpoint) {
            $name = str_pad($endpoint['name'], 11);
            $status = $this->formatStatus($endpoint['status'], 7);
            $columnCount = str_pad(count($endpoint['columns']), 7);
            $created = str_pad(substr($endpoint['created_at'] ?? 'Unknown', 0, 11), 11);
            $size = str_pad($this->formatFileSize($endpoint['file_size']), 15);
            
            $this->output("│ {$name} │ {$status} │ {$columnCount} │ {$created} │ {$size} │");
            
            if ($showUrls) {
                $url = "{$baseUrl}/{$version}/{$endpoint['name']}";
                $this->output("│ URL: " . str_pad($url, 60) . " │", 'cyan');
            }
            
            if ($showDetails && !empty($endpoint['columns'])) {
                $this->output("│ Columns: " . str_pad($this->formatColumnList($endpoint['columns']), 57) . " │", 'yellow');
            }
        }
        
        $this->output("└─────────────┴─────────┴─────────┴─────────────┴─────────────────┘", 'cyan');
        $this->output("");
    }
    
    private function formatStatus(string $status, int $width): string
    {
        $colors = [
            'complete' => '✅ OK   ',
            'partial' => '⚠️  PART',
            'missing' => '❌ MISS',
            'unknown' => '❓ UNK '
        ];
        
        return str_pad($colors[$status] ?? $status, $width);
    }
    
    private function formatFileSize(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        
        $units = ['B', 'KB', 'MB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 1) . ' ' . $units[$pow];
    }
    
    private function formatColumnList(array $columns): string
    {
        if (empty($columns)) {
            return 'None';
        }
        
        $columnNames = array_column($columns, 'name');
        $result = implode(', ', array_slice($columnNames, 0, 3));
        
        if (count($columnNames) > 3) {
            $result .= ' +' . (count($columnNames) - 3);
        }
        
        return $result;
    }
    
    private function displayJSON(array $endpoints): void
    {
        $output = [
            'total_endpoints' => count($endpoints),
            'timestamp' => date('c'),
            'endpoints' => $endpoints
        ];
        
        $this->output(json_encode($output, JSON_PRETTY_PRINT));
    }
    
    private function displayCSV(array $endpoints): void
    {
        // Header
        $this->output("Name,Status,Columns,Created,Size,Fillable,Custom Methods");
        
        foreach ($endpoints as $endpoint) {
            $row = [
                $endpoint['name'],
                $endpoint['status'],
                count($endpoint['columns']),
                $endpoint['created_at'] ?? '',
                $endpoint['file_size'],
                implode(';', $endpoint['fillable']),
                implode(';', $endpoint['custom_methods'])
            ];
            
            $this->output(implode(',', $row));
        }
    }
    
    private function displaySummary(array $endpoints): void
    {
        $total = count($endpoints);
        $complete = count(array_filter($endpoints, fn($e) => $e['status'] === 'complete'));
        $partial = count(array_filter($endpoints, fn($e) => $e['status'] === 'partial'));
        $missing = count(array_filter($endpoints, fn($e) => $e['status'] === 'missing'));
        
        $totalColumns = array_sum(array_map(fn($e) => count($e['columns']), $endpoints));
        $totalSize = array_sum(array_column($endpoints, 'file_size'));
        
        $this->output("📊 Summary:", 'blue');
        $this->output("   Total Endpoints: {$total}");
        $this->output("   Complete: {$complete} | Partial: {$partial} | Missing: {$missing}");
        $this->output("   Total Columns: {$totalColumns}");
        $this->output("   Total Size: " . $this->formatFileSize($totalSize));
        $this->output("");
        
        if ($missing > 0) {
            $this->output("⚠️  Missing endpoints detected. Run setup or recreate them.", 'yellow');
        }
        
        if ($total > 0) {
            $this->output("🎯 Available Commands:", 'blue');
            $this->output("   php bin/flexiapi update:endpoint <name>   # Modify endpoint");
            $this->output("   php bin/flexiapi generate:postman         # Create Postman collection");
            $this->output("   php bin/flexiapi export:sql               # Export database schema");
            $this->output("   php bin/flexiapi serve                    # Start development server");
        }
    }
    
    protected function loadConfig(): array
    {
        $configFile = 'config/config.php';
        if (file_exists($configFile)) {
            return require $configFile;
        }
        
        return [
            'api' => [
                'base_url' => 'http://localhost:8000/api',
                'version' => 'v1'
            ]
        ];
    }
}