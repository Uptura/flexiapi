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
        // Use proper path methods like CreateEndpointCommand
        $controllerPath = $this->getEndpointsPath() . DIRECTORY_SEPARATOR . ucfirst($endpointName) . 'Controller.php';
        $routesPath = $this->getEndpointsPath() . DIRECTORY_SEPARATOR . $endpointName . 'Routes.php';
        $sqlPath = $this->getSqlPath() . DIRECTORY_SEPARATOR . $endpointName . '.sql';
        
        // Endpoint exists if any of the expected files exist (not all required)
        return file_exists($controllerPath) || file_exists($routesPath) || file_exists($sqlPath);
    }
    
    private function listAvailableEndpoints(): void
    {
        $this->output("\n📋 Available endpoints:", 'info');
        
        $endpointsPath = $this->getEndpointsPath();
        if (!is_dir($endpointsPath)) {
            $this->output("  No endpoints found.", 'yellow');
            return;
        }
        
        $controllers = glob($endpointsPath . DIRECTORY_SEPARATOR . '*Controller.php');
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
        $sqlFile = $this->getSqlPath() . DIRECTORY_SEPARATOR . $endpointName . '.sql';
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
            $this->output("  4. Configure encryption");
            $this->output("  5. Regenerate controller");
            $this->output("  6. View current schema");
            $this->output("  7. Exit");
            $this->output("");
            
            $choice = $this->ask("Select option (1-7)", "7");
            
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
                    $this->configureEncryption($endpointName, $currentColumns);
                    break;
                case '5':
                    $this->regenerateController($endpointName, $currentColumns);
                    break;
                case '6':
                    $this->viewSchema($endpointName);
                    break;
                case '7':
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
        
        $controllerFile = "endpoints/" . ucfirst($endpointName) . "Controller.php";
        
        // Preserve existing encryption and hidden settings
        $currentEncrypted = $this->getCurrentEncryptedFields($controllerFile);
        $currentHidden = $this->getCurrentHiddenFields($controllerFile);
        
        $fillable = array_column($currentColumns, 'name');
        $validationRules = [];
        
        foreach ($currentColumns as $column) {
            $rules = [];
            if (!$column['nullable']) $rules[] = 'required';
            if ($column['unique']) $rules[] = 'unique';
            
            $validationRules[$column['name']] = implode('|', $rules);
        }
        
        $controllerContent = $this->generateControllerContent($endpointName, $fillable, $validationRules, $currentEncrypted, $currentHidden);
        
        file_put_contents($controllerFile, $controllerContent);
        
        $this->output("✅ Controller regenerated successfully!", 'green');
        if (!empty($currentEncrypted)) {
            $this->output("🔐 Preserved encryption for: " . implode(', ', $currentEncrypted), 'yellow');
        }
    }
    
    private function generateControllerContent(string $endpointName, array $fillable, array $validationRules, array $encrypted = [], array $hidden = []): string
    {
        $className = ucfirst($endpointName) . 'Controller';
        $fillableStr = "'" . implode("', '", $fillable) . "'";
        $encryptedStr = empty($encrypted) ? '[]' : "['" . implode("', '", $encrypted) . "']";
        $hiddenStr = empty($hidden) ? '[]' : "['" . implode("', '", $hidden) . "']";
        
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
    protected array \$encrypted = {$encryptedStr};
    protected array \$hidden = {$hiddenStr};

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
    
    private function configureEncryption(string $endpointName, array $currentColumns): void
    {
        $this->output("\n🔐 Configure Field Encryption", 'blue');
        $this->output("Select fields that should be encrypted when stored in the database:");
        $this->output("");
        
        $controllerFile = "endpoints/" . ucfirst($endpointName) . "Controller.php";
        $currentEncrypted = $this->getCurrentEncryptedFields($controllerFile);
        
        // Display current encrypted fields
        if (!empty($currentEncrypted)) {
            $this->output("Currently encrypted fields:", 'yellow');
            foreach ($currentEncrypted as $field) {
                $this->output("  ✓ {$field}", 'green');
            }
            $this->output("");
        }
        
        // Show available fields
        $availableFields = array_column($currentColumns, 'name');
        $this->output("Available fields:", 'cyan');
        foreach ($availableFields as $index => $field) {
            $encrypted = in_array($field, $currentEncrypted) ? " [ENCRYPTED]" : "";
            $this->output("  " . ($index + 1) . ". {$field}{$encrypted}");
        }
        $this->output("");
        
        // Ask user to select fields for encryption
        $this->output("Enter field numbers to encrypt (comma-separated, e.g., 1,3,5) or 'none' to clear all:");
        $selection = $this->ask("Fields to encrypt", "none");
        
        $newEncrypted = [];
        if ($selection !== 'none' && !empty($selection)) {
            $selectedNumbers = array_map('trim', explode(',', $selection));
            foreach ($selectedNumbers as $num) {
                $index = (int)$num - 1;
                if (isset($availableFields[$index])) {
                    $newEncrypted[] = $availableFields[$index];
                }
            }
        }
        
        // Update the controller with new encryption configuration
        $this->updateControllerEncryption($controllerFile, $newEncrypted);
        
        $this->output("✅ Encryption configuration updated!", 'green');
        if (!empty($newEncrypted)) {
            $this->output("Encrypted fields: " . implode(', ', $newEncrypted), 'yellow');
        } else {
            $this->output("No fields will be encrypted", 'yellow');
        }
    }
    
    private function getCurrentEncryptedFields(string $controllerFile): array
    {
        if (!file_exists($controllerFile)) {
            return [];
        }
        
        $content = file_get_contents($controllerFile);
        
        // Look for the $encrypted array
        if (preg_match('/protected\s+array\s+\$encrypted\s*=\s*\[(.*?)\];/s', $content, $matches)) {
            $arrayContent = $matches[1];
            preg_match_all("/'([^']+)'/", $arrayContent, $fieldMatches);
            return $fieldMatches[1] ?? [];
        }
        
        return [];
    }
    
    private function getCurrentHiddenFields(string $controllerFile): array
    {
        if (!file_exists($controllerFile)) {
            return [];
        }
        
        $content = file_get_contents($controllerFile);
        
        // Look for the $hidden array
        if (preg_match('/protected\s+array\s+\$hidden\s*=\s*\[(.*?)\];/s', $content, $matches)) {
            $arrayContent = $matches[1];
            preg_match_all("/'([^']+)'/", $arrayContent, $fieldMatches);
            return $fieldMatches[1] ?? [];
        }
        
        return [];
    }
    
    private function updateControllerEncryption(string $controllerFile, array $encryptedFields): void
    {
        $content = file_get_contents($controllerFile);
        
        // Create the encrypted array string
        $encryptedStr = empty($encryptedFields) ? '[]' : "['" . implode("', '", $encryptedFields) . "']";
        
        // Check if $encrypted array already exists
        if (preg_match('/protected\s+array\s+\$encrypted\s*=\s*\[.*?\];/s', $content)) {
            // Replace existing encrypted array
            $content = preg_replace(
                '/protected\s+array\s+\$encrypted\s*=\s*\[.*?\];/s',
                "protected array \$encrypted = {$encryptedStr};",
                $content
            );
        } else {
            // Add encrypted array after fillable array
            if (preg_match('/protected\s+array\s+\$fillable\s*=\s*\[.*?\];/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
                $insertPos = $matches[0][1] + strlen($matches[0][0]);
                $content = substr_replace($content, "\n    protected array \$encrypted = {$encryptedStr};", $insertPos, 0);
            }
        }
        
        // Also add hidden array if it doesn't exist
        if (!preg_match('/protected\s+array\s+\$hidden\s*=\s*\[.*?\];/s', $content)) {
            if (preg_match('/protected\s+array\s+\$encrypted\s*=\s*\[.*?\];/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
                $insertPos = $matches[0][1] + strlen($matches[0][0]);
                $content = substr_replace($content, "\n    protected array \$hidden = [];", $insertPos, 0);
            }
        }
        
        file_put_contents($controllerFile, $content);
    }
}