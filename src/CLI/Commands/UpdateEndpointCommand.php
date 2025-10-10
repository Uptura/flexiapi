<?php

namespace FlexiAPI\CLI\Commands;

class UpdateEndpointCommand extends BaseCommand
{
    protected string $signature = 'update:endpoint';
    protected string $description = 'Update an existing API endpoint';
    
    public function execute(array $args): int
    {
        $this->handle($args);
        return 0;
    }
    
    public function handle(array $args): void
    {
        $this->output("🔧 Update Existing API Endpoint", 'header');
        $this->output("");
        
        // Get endpoint name
        $endpointName = $args[0] ?? $this->ask("Enter endpoint name to update");
        if (empty($endpointName)) {
            $this->output("❌ Endpoint name is required!", 'error');
            return;
        }
        
        $endpointName = strtolower(trim($endpointName));
        
        // Check if endpoint exists
        if (!$this->endpointExists($endpointName)) {
            $this->output("❌ Endpoint '{$endpointName}' not found!", 'error');
            $this->listAvailableEndpoints();
            return;
        }
        
        $this->output("📝 Updating endpoint: {$endpointName}", 'info');
        $this->output("");
        
        // Get current columns
        $currentColumns = $this->getCurrentColumns($endpointName);
        $this->displayCurrentColumns($currentColumns);
        
        // Show update options
        $this->showUpdateMenu($endpointName, $currentColumns);
    }
    
    private function endpointExists(string $endpointName): bool
    {
        $controllerFile = "endpoints/" . ucfirst($endpointName) . "Controller.php";
        $sqlFile = "sql/{$endpointName}.sql";
        $routesFile = "endpoints/{$endpointName}Routes.php";
        
        return file_exists($controllerFile) && file_exists($sqlFile) && file_exists($routesFile);
    }
    
    private function listAvailableEndpoints(): void
    {
        $this->output("\n📋 Available endpoints:", 'info');
        
        if (!is_dir('endpoints')) {
            $this->output("  No endpoints found.", 'yellow');
            return;
        }
        
        $controllers = glob('endpoints/*Controller.php');
        if (empty($controllers)) {
            $this->output("  No endpoints found.", 'yellow');
            return;
        }
        
        foreach ($controllers as $controller) {
            $name = basename($controller, 'Controller.php');
            $endpointName = strtolower($name);
            $this->output("  • {$endpointName}", 'cyan');
        }
    }
    
    private function getCurrentColumns(string $endpointName): array
    {
        $sqlFile = "sql/{$endpointName}.sql";
        $content = file_get_contents($sqlFile);
        
        // Parse SQL to extract columns
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
                'type' => $columnType,
                'nullable' => str_contains($columnType, 'NULL'),
                'unique' => str_contains($columnType, 'UNIQUE')
            ];
        }
        
        return $columns;
    }
    
    private function displayCurrentColumns(array $columns): void
    {
        $this->output("📋 Current columns:", 'info');
        if (empty($columns)) {
            $this->output("  No custom columns found.", 'yellow');
            return;
        }
        
        foreach ($columns as $index => $column) {
            $flags = [];
            if (!$column['nullable']) $flags[] = 'required';
            if ($column['unique']) $flags[] = 'unique';
            
            $flagsStr = !empty($flags) ? ' (' . implode(', ', $flags) . ')' : '';
            $this->output("  " . ($index + 1) . ". {$column['name']} - {$column['type']}{$flagsStr}", 'cyan');
        }
        $this->output("");
    }
    
    private function showUpdateMenu(string $endpointName, array $currentColumns): void
    {
        while (true) {
            $this->output("🔧 Update Options:", 'blue');
            $this->output("  1. Add new column");
            $this->output("  2. Modify existing column");
            $this->output("  3. Remove column");
            $this->output("  4. Regenerate controller");
            $this->output("  5. View current schema");
            $this->output("  6. Exit");
            $this->output("");
            
            $choice = $this->ask("Select option (1-6)", "6");
            
            switch ($choice) {
                case '1':
                    $this->addColumn($endpointName, $currentColumns);
                    break;
                case '2':
                    $this->modifyColumn($endpointName, $currentColumns);
                    break;
                case '3':
                    $this->removeColumn($endpointName, $currentColumns);
                    break;
                case '4':
                    $this->regenerateController($endpointName, $currentColumns);
                    break;
                case '5':
                    $this->viewSchema($endpointName);
                    break;
                case '6':
                default:
                    $this->output("\n✅ Update completed!", 'green');
                    return;
            }
            
            // Refresh columns after each operation
            $currentColumns = $this->getCurrentColumns($endpointName);
            $this->output("");
        }
    }
    
    private function addColumn(string $endpointName, array &$currentColumns): void
    {
        $this->output("\n➕ Adding new column", 'blue');
        
        $columnName = $this->ask("Column name");
        if (empty($columnName)) {
            $this->output("❌ Column name is required!", 'error');
            return;
        }
        
        // Check if column already exists
        foreach ($currentColumns as $col) {
            if ($col['name'] === $columnName) {
                $this->output("❌ Column '{$columnName}' already exists!", 'error');
                return;
            }
        }
        
        $columnType = $this->selectColumnType();
        $isRequired = $this->confirm("Is '{$columnName}' required?");
        $isUnique = $this->confirm("Should '{$columnName}' be unique?");
        
        // Add to SQL
        $this->addColumnToSQL($endpointName, $columnName, $columnType, $isRequired, $isUnique);
        
        // Update controller
        $this->updateControllerFillable($endpointName, $columnName, 'add');
        
        $this->output("✅ Column '{$columnName}' added successfully!", 'green');
    }
    
    private function modifyColumn(string $endpointName, array $currentColumns): void
    {
        if (empty($currentColumns)) {
            $this->output("❌ No columns to modify!", 'error');
            return;
        }
        
        $this->output("\n✏️ Modify existing column", 'blue');
        $this->displayCurrentColumns($currentColumns);
        
        $columnIndex = (int)$this->ask("Select column number to modify") - 1;
        
        if (!isset($currentColumns[$columnIndex])) {
            $this->output("❌ Invalid column selection!", 'error');
            return;
        }
        
        $oldColumn = $currentColumns[$columnIndex];
        
        $this->output("Modifying column: {$oldColumn['name']}");
        $newName = $this->ask("New column name", $oldColumn['name']);
        $newType = $this->selectColumnType();
        $isRequired = $this->confirm("Is '{$newName}' required?", !$oldColumn['nullable']);
        $isUnique = $this->confirm("Should '{$newName}' be unique?", $oldColumn['unique']);
        
        // Update SQL
        $this->modifyColumnInSQL($endpointName, $oldColumn['name'], $newName, $newType, $isRequired, $isUnique);
        
        // Update controller if name changed
        if ($oldColumn['name'] !== $newName) {
            $this->updateControllerFillable($endpointName, $oldColumn['name'], 'remove');
            $this->updateControllerFillable($endpointName, $newName, 'add');
        }
        
        $this->output("✅ Column modified successfully!", 'green');
    }
    
    private function removeColumn(string $endpointName, array $currentColumns): void
    {
        if (empty($currentColumns)) {
            $this->output("❌ No columns to remove!", 'error');
            return;
        }
        
        $this->output("\n🗑️ Remove column", 'blue');
        $this->displayCurrentColumns($currentColumns);
        
        $columnIndex = (int)$this->ask("Select column number to remove") - 1;
        
        if (!isset($currentColumns[$columnIndex])) {
            $this->output("❌ Invalid column selection!", 'error');
            return;
        }
        
        $column = $currentColumns[$columnIndex];
        
        if (!$this->confirm("Are you sure you want to remove '{$column['name']}'? This action cannot be undone.")) {
            $this->output("❌ Operation cancelled.", 'yellow');
            return;
        }
        
        // Remove from SQL
        $this->removeColumnFromSQL($endpointName, $column['name']);
        
        // Update controller
        $this->updateControllerFillable($endpointName, $column['name'], 'remove');
        
        $this->output("✅ Column '{$column['name']}' removed successfully!", 'green');
    }
    
    private function selectColumnType(): string
    {
        $this->output("  Select column type:");
        $this->output("    1. VARCHAR(255)");
        $this->output("    2. TEXT");
        $this->output("    3. INT");
        $this->output("    4. DECIMAL(10,2)");
        $this->output("    5. BOOLEAN");
        $this->output("    6. DATETIME");
        $this->output("    7. JSON");
        $this->output("    8. Custom type");
        
        $choice = $this->ask("Enter choice (1-8)", "1");
        
        $types = [
            '1' => 'VARCHAR(255)',
            '2' => 'TEXT',
            '3' => 'INT',
            '4' => 'DECIMAL(10,2)',
            '5' => 'BOOLEAN',
            '6' => 'DATETIME',
            '7' => 'JSON'
        ];
        
        if (isset($types[$choice])) {
            return $types[$choice];
        } elseif ($choice === '8') {
            return $this->ask("Enter custom type");
        }
        
        return 'VARCHAR(255)';
    }
    
    private function addColumnToSQL(string $endpointName, string $columnName, string $columnType, bool $isRequired, bool $isUnique): void
    {
        $sqlFile = "sql/{$endpointName}.sql";
        $content = file_get_contents($sqlFile);
        
        $nullable = $isRequired ? 'NOT NULL' : 'NULL';
        $unique = $isUnique ? 'UNIQUE' : '';
        
        $newColumn = "  `{$columnName}` {$columnType} {$nullable} {$unique},";
        
        // Insert before the timestamp columns
        $content = preg_replace(
            '/(\s+`created_at`\s+timestamp)/i',
            "\n{$newColumn}\n$1",
            $content
        );
        
        file_put_contents($sqlFile, $content);
    }
    
    private function modifyColumnInSQL(string $endpointName, string $oldName, string $newName, string $columnType, bool $isRequired, bool $isUnique): void
    {
        $sqlFile = "sql/{$endpointName}.sql";
        $content = file_get_contents($sqlFile);
        
        $nullable = $isRequired ? 'NOT NULL' : 'NULL';
        $unique = $isUnique ? 'UNIQUE' : '';
        
        $newColumn = "`{$newName}` {$columnType} {$nullable} {$unique}";
        
        // Replace the old column definition
        $content = preg_replace(
            '/`' . preg_quote($oldName) . '`\s+[^,\n]+/i',
            $newColumn,
            $content
        );
        
        file_put_contents($sqlFile, $content);
    }
    
    private function removeColumnFromSQL(string $endpointName, string $columnName): void
    {
        $sqlFile = "sql/{$endpointName}.sql";
        $content = file_get_contents($sqlFile);
        
        // Remove the column line
        $content = preg_replace(
            '/\s*`' . preg_quote($columnName) . '`\s+[^,\n]+,?\n?/i',
            '',
            $content
        );
        
        file_put_contents($sqlFile, $content);
    }
    
    private function updateControllerFillable(string $endpointName, string $columnName, string $action): void
    {
        $controllerFile = "endpoints/" . ucfirst($endpointName) . "Controller.php";
        $content = file_get_contents($controllerFile);
        
        if ($action === 'add') {
            // Add to fillable array
            $content = preg_replace(
                '/(protected array \$fillable = \[)([^\]]*?)(\];)/s',
                '$1$2\'' . $columnName . '\', $3',
                $content
            );
        } elseif ($action === 'remove') {
            // Remove from fillable array
            $content = preg_replace(
                '/[\'"]\s*' . preg_quote($columnName) . '\s*[\'"],?\s*/i',
                '',
                $content
            );
        }
        
        file_put_contents($controllerFile, $content);
    }
    
    private function regenerateController(string $endpointName, array $currentColumns): void
    {
        $this->output("\n🔄 Regenerating controller", 'blue');
        
        $fillable = array_column($currentColumns, 'name');
        $validationRules = [];
        
        foreach ($currentColumns as $column) {
            $rules = [];
            if (!$column['nullable']) $rules[] = 'required';
            if ($column['unique']) $rules[] = 'unique';
            
            $validationRules[$column['name']] = implode('|', $rules);
        }
        
        $controllerContent = $this->generateControllerContent($endpointName, $fillable, $validationRules);
        
        $controllerFile = "endpoints/" . ucfirst($endpointName) . "Controller.php";
        file_put_contents($controllerFile, $controllerContent);
        
        $this->output("✅ Controller regenerated successfully!", 'green');
    }
    
    private function generateControllerContent(string $endpointName, array $fillable, array $validationRules): string
    {
        $className = ucfirst($endpointName) . 'Controller';
        $fillableStr = "'" . implode("', '", $fillable) . "'";
        
        $rulesArray = [];
        foreach ($validationRules as $field => $rules) {
            $rulesArray[] = "            '{$field}' => '{$rules}'";
        }
        $rulesStr = implode(",\n", $rulesArray);
        
        return <<<PHP
<?php

namespace FlexiAPI\Endpoints;

use FlexiAPI\Core\BaseEndpointController;
use FlexiAPI\Utils\Response;
use FlexiAPI\Utils\Validator;

/**
 * {$className} - Auto-generated by FlexiAPI CLI
 * Endpoint: /{$endpointName}
 * Updated on: " . date('Y-m-d H:i:s') . "
 */
class {$className} extends BaseEndpointController
{
    protected string \$tableName = '{$endpointName}';
    protected array \$fillable = [{$fillableStr}];

    protected function getValidationRules(): array
    {
        return [
{$rulesStr}
        ];
    }

    // Add custom methods here
}
PHP;
    }
    
    private function viewSchema(string $endpointName): void
    {
        $this->output("\n📋 Current Schema for '{$endpointName}':", 'blue');
        
        $sqlFile = "sql/{$endpointName}.sql";
        if (!file_exists($sqlFile)) {
            $this->output("❌ SQL file not found!", 'error');
            return;
        }
        
        $content = file_get_contents($sqlFile);
        $this->output($content, 'cyan');
    }
}