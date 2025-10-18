<?php

namespace FlexiAPI\CLI\Commands;

class CreateEndpointCommand extends BaseCommand
{
    public function execute(array $args): int
    {
        $this->output("🚀 Creating New API Endpoint", 'header');
        $this->output("");
        
        // Get endpoint name
        $endpointName = $this->getEndpointName($args);
        if (!$endpointName) {
            return 1;
        }
        
        // Check if endpoint already exists
        if ($this->endpointExists($endpointName)) {
            $this->output("❌ Endpoint '{$endpointName}' already exists!", 'error');
            if (!$this->confirm("Do you want to update it instead?")) {
                return 1;
            }
            return $this->updateExistingEndpoint($endpointName);
        }
        
        // Get columns
        $columns = $this->getColumns();
        if (empty($columns)) {
            $this->output("❌ No columns specified. Endpoint creation cancelled.", 'error');
            return 1;
        }
        
        // Create endpoint
        $success = $this->createEndpoint($endpointName, $columns);
        
        if ($success) {
            $this->output("✅ Endpoint '{$endpointName}' created successfully!", 'success');
            $this->output("");
            $this->output("📁 Files generated:", 'info');
            $this->output("  - SQL: sql/{$endpointName}.sql");
            $this->output("  - Controller: endpoints/{$endpointName}Controller.php");
            $this->output("  - Routes: endpoints/{$endpointName}Routes.php");
            $this->output("");
            $this->output("🔧 Next steps:", 'info');
            $this->output("  1. Run 'flexiapi setup' to configure database");
            $this->output("  2. Import SQL file: mysql -u user -p database < sql/{$endpointName}.sql");
            $this->output("  3. Run 'flexiapi serve' to start development server");
            return 0;
        } else {
            $this->output("❌ Failed to create endpoint!", 'error');
            return 1;
        }
    }
    
    private function getEndpointName(array $args): ?string
    {
        $name = $args[0] ?? null;
        
        if (!$name) {
            $name = $this->input("Enter endpoint name (e.g., 'users', 'products'):");
        }
        
        if (empty($name)) {
            $this->output("❌ Endpoint name cannot be empty!", 'error');
            return null;
        }
        
        $name = strtolower(trim($name));
        
        if (!$this->validateEndpointName($name)) {
            $this->output("❌ Invalid endpoint name! Use only letters, numbers, and underscores.", 'error');
            return null;
        }
        
        return $name;
    }
    
    private function getColumns(): array
    {
        $this->output("📋 Configure Database Columns", 'info');
        $this->output("Enter column names separated by commas (e.g., name,description,price)");
        $this->output("Or enter them one by one (press Enter with empty input to finish)");
        $this->output("");
        
        $columnsInput = $this->input("Columns (comma-separated) or press Enter for manual input:");
        
        if (!empty($columnsInput)) {
            $columnNames = array_map('trim', explode(',', $columnsInput));
            return $this->processColumnNames($columnNames);
        }
        
        return $this->getColumnsManually();
    }
    
    private function getColumnsManually(): array
    {
        $columns = [];
        $counter = 1;
        
        while (true) {
            $columnName = $this->input("Column {$counter} name (or press Enter to finish):");
            
            if (empty($columnName)) {
                break;
            }
            
            $sanitized = $this->sanitizeColumnName($columnName);
            if ($sanitized !== $columnName) {
                $this->output("  → Sanitized to: {$sanitized}", 'warning');
            }
            
            $type = $this->getColumnType();
            $columns[] = [
                'name' => $sanitized,
                'type' => $type,
                'required' => $this->confirm("  Is '{$sanitized}' required?"),
                'unique' => $type !== 'TEXT' && $this->confirm("  Should '{$sanitized}' be unique?")
            ];
            
            $counter++;
        }
        
        return $columns;
    }
    
    private function processColumnNames(array $columnNames): array
    {
        $columns = [];
        
        foreach ($columnNames as $name) {
            if (empty($name)) continue;
            
            $sanitized = $this->sanitizeColumnName($name);
            $this->output("📝 Configuring column: {$sanitized}", 'info');
            
            $type = $this->getColumnType();
            $columns[] = [
                'name' => $sanitized,
                'type' => $type,
                'required' => $this->confirm("  Is '{$sanitized}' required?"),
                'unique' => $type !== 'TEXT' && $this->confirm("  Should '{$sanitized}' be unique?")
            ];
        }
        
        return $columns;
    }
    
    private function getColumnType(): string
    {
        $this->output("  Select column type:");
        $types = [
            '1' => 'VARCHAR(255)',
            '2' => 'TEXT',
            '3' => 'INT',
            '4' => 'DECIMAL(10,2)',
            '5' => 'BOOLEAN',
            '6' => 'DATETIME',
            '7' => 'JSON'
        ];
        
        foreach ($types as $key => $type) {
            $this->output("    {$key}. {$type}");
        }
        
        $choice = $this->input("  Enter choice (1-7) or custom type:");
        
        if (isset($types[$choice])) {
            return $types[$choice];
        }
        
        return empty($choice) ? 'VARCHAR(255)' : $choice;
    }
    
    private function endpointExists(string $name): bool
    {
        // Check if controller file exists
        $controllerPath = $this->getEndpointsPath() . DIRECTORY_SEPARATOR . ucfirst($name) . 'Controller.php';
        $routesPath = $this->getEndpointsPath() . DIRECTORY_SEPARATOR . $name . 'Routes.php';
        $sqlPath = $this->getSqlPath() . DIRECTORY_SEPARATOR . $name . '.sql';
        
        // Endpoint exists if any of the expected files exist
        return file_exists($controllerPath) || file_exists($routesPath) || file_exists($sqlPath);
    }
    
    private function createEndpoint(string $name, array $columns): bool
    {
        try {
            // Generate SQL file
            $this->generateSqlFile($name, $columns);
            
            // Generate Controller
            $this->generateController($name, $columns);
            
            // Generate Routes
            $this->generateRoutes($name);
            
            // Update config
            $this->updateConfigWithEndpoint($name, $columns);
            
            return true;
        } catch (\Exception $e) {
            $this->output("Error: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    private function generateSqlFile(string $name, array $columns): void
    {
        $tableName = $name;
        $sql = "-- FlexiAPI Generated SQL for '{$name}' endpoint\n";
        $sql .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";
        
        $sql .= "CREATE TABLE IF NOT EXISTS `{$tableName}` (\n";
        $sql .= "  `id` int(11) NOT NULL AUTO_INCREMENT,\n";
        
        foreach ($columns as $column) {
            $null = $column['required'] ? 'NOT NULL' : 'NULL';
            $unique = $column['unique'] ? ' UNIQUE' : '';
            $sql .= "  `{$column['name']}` {$column['type']} {$null}{$unique},\n";
        }
        
        $sql .= "  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n";
        $sql .= "  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n";
        $sql .= "  PRIMARY KEY (`id`)\n";
        $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n";
        
        // Add indexes for better performance
        foreach ($columns as $column) {
            if ($column['unique'] || $column['required']) {
                $sql .= "CREATE INDEX `idx_{$tableName}_{$column['name']}` ON `{$tableName}` (`{$column['name']}`);\n";
            }
        }
        
        $sqlPath = $this->getSqlPath() . DIRECTORY_SEPARATOR . "{$name}.sql";
        file_put_contents($sqlPath, $sql);
    }
    
    private function generateController(string $name, array $columns): void
    {
        $className = ucfirst($name) . 'Controller';
        $tableName = $name;
        
        $controller = "<?php\n\n";
        $controller .= "namespace FlexiAPI\\Endpoints;\n\n";
        $controller .= "use FlexiAPI\\Core\\BaseEndpointController;\n";
        $controller .= "use FlexiAPI\\Utils\\Response;\n";
        $controller .= "use FlexiAPI\\Utils\\Validator;\n\n";
        $controller .= "/**\n";
        $controller .= " * {$className} - Auto-generated by FlexiAPI CLI\n";
        $controller .= " * Endpoint: /{$name}\n";
        $controller .= " * Generated on: " . date('Y-m-d H:i:s') . "\n";
        $controller .= " */\n";
        $controller .= "class {$className} extends BaseEndpointController\n";
        $controller .= "{\n";
        $controller .= "    protected string \$tableName = '{$tableName}';\n";
        $controller .= "    protected array \$fillable = ['" . implode("', '", array_column($columns, 'name')) . "'];\n\n";
        
        // Validation rules
        $controller .= "    protected function getValidationRules(): array\n";
        $controller .= "    {\n";
        $controller .= "        return [\n";
        foreach ($columns as $column) {
            $rules = [];
            if ($column['required']) $rules[] = 'required';
            if ($column['unique']) $rules[] = 'unique:' . $tableName;
            if ($column['type'] === 'INT') $rules[] = 'integer';
            if (strpos($column['type'], 'DECIMAL') !== false) $rules[] = 'numeric';
            
            $rulesStr = !empty($rules) ? "'" . implode('|', $rules) . "'" : "''";
            $controller .= "            '{$column['name']}' => {$rulesStr},\n";
        }
        $controller .= "        ];\n";
        $controller .= "    }\n\n";
        
        // Custom methods can be added here
        $controller .= "    // Add custom methods here\n";
        $controller .= "}\n";
        
        $controllerPath = $this->getEndpointsPath() . DIRECTORY_SEPARATOR . "{$className}.php";
        file_put_contents($controllerPath, $controller);
    }
    
    private function generateRoutes(string $name): void
    {
        $className = ucfirst($name) . 'Controller';
        
        $routes = "<?php\n\n";
        $routes .= "// Routes for {$name} endpoint\n";
        $routes .= "// Auto-generated by FlexiAPI CLI on " . date('Y-m-d H:i:s') . "\n\n";
        $routes .= "use FlexiAPI\\Endpoints\\{$className};\n\n";
        $routes .= "\$controller = new {$className}(\$db, \$config);\n\n";
        $routes .= "// REST endpoints for {$name}\n";
        $routes .= "\$router->get('/{$name}', function() use (\$controller) {\n";
        $routes .= "    \$controller->index();\n";
        $routes .= "});\n\n";
        $routes .= "\$router->post('/{$name}', function() use (\$controller) {\n";
        $routes .= "    \$controller->store();\n";
        $routes .= "});\n\n";
        $routes .= "\$router->get('/{$name}/{id}', function(\$id) use (\$controller) {\n";
        $routes .= "    \$controller->show(\$id);\n";
        $routes .= "});\n\n";
        $routes .= "\$router->put('/{$name}/{id}', function(\$id) use (\$controller) {\n";
        $routes .= "    \$controller->update(\$id);\n";
        $routes .= "});\n\n";
        $routes .= "\$router->delete('/{$name}/{id}', function(\$id) use (\$controller) {\n";
        $routes .= "    \$controller->destroy(\$id);\n";
        $routes .= "});\n";
        
        $routesPath = $this->getEndpointsPath() . DIRECTORY_SEPARATOR . "{$name}Routes.php";
        file_put_contents($routesPath, $routes);
    }
    
    private function updateConfigWithEndpoint(string $name, array $columns): void
    {
        $config = $this->loadConfig();
        $config['endpoints'][$name] = [
            'table' => $name,
            'columns' => $columns,
            'created_at' => date('Y-m-d H:i:s'),
            'controller' => ucfirst($name) . 'Controller'
        ];
        $this->saveConfig($config);
    }
    
    private function updateExistingEndpoint(string $name): int
    {
        // This would call the update command
        $this->output("Redirecting to update command...", 'info');
        $updateCommand = new UpdateEndpointCommand();
        return $updateCommand->execute([$name]);
    }
}