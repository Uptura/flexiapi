<?php

namespace FlexiAPI\Validators;

class InputValidator
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * ✅ Validate GET (Select rows)
     */
    public function validateGet(array $input): array
    {
        $result = [];

        // table
        $result['table'] = $this->requireTable($input['table'] ?? null);

        // columns
        $columns = $input['columns'] ?? ['*'];
        if (is_string($columns)) {
            $columns = array_map('trim', explode(',', $columns));
        }
        if (!is_array($columns) || empty($columns)) {
            $columns = ['*'];
        }
        $result['columns'] = $this->sanitizeColumns($columns);

        // condition
        $result['condition'] = $this->parseCondition($input['condition'] ?? []);

        // pagination
        $page = (int)($input['page'] ?? 1);
        if ($page < 1) $page = 1;
        $limit = (int)($input['limit'] ?? $this->config['api']['default_limit']);
        if ($limit < 1) $limit = $this->config['api']['default_limit'];
        $limit = min($limit, $this->config['api']['max_limit']);

        $result['page'] = $page;
        $result['limit'] = $limit;

        // optional order
        $result['order'] = is_array($input['order'] ?? null) ? $input['order'] : [];

        return $result;
    }

    /**
     * ✅ Validate Create (Insert rows)
     */
    public function validateCreate(array $input): array
    {
        $result = [];
        $result['table'] = $this->requireTable($input['table'] ?? null);

        if (empty($input['data'])) {
            throw new \InvalidArgumentException("Missing required field: data");
        }

        $rows = is_array($input['data']) ? $input['data'] : [$input['data']];
        if (!isset($rows[0]) || !is_array($rows[0])) {
            throw new \InvalidArgumentException("Invalid data format, must be array of objects");
        }

        $result['data'] = $rows;
        return $result;
    }

    /**
     * ✅ Validate Update
     */
    public function validateUpdate(array $input): array
    {
        $result = [];
        $result['table'] = $this->requireTable($input['table'] ?? null);

        if (empty($input['where']) || !is_array($input['where'])) {
            throw new \InvalidArgumentException("Missing or invalid where condition");
        }
        if (empty($input['data']) || !is_array($input['data'])) {
            throw new \InvalidArgumentException("Missing or invalid data for update");
        }

        $result['where'] = $input['where'];
        $result['data'] = $input['data'];
        return $result;
    }

    /**
     * ✅ Validate Delete
     */
    public function validateDelete(array $input): array
    {
        $result = [];
        $result['table'] = $this->requireTable($input['table'] ?? null);

        if (empty($input['column']) || !preg_match('/^[a-zA-Z0-9_]+$/', $input['column'])) {
            throw new \InvalidArgumentException("Missing or invalid column for delete");
        }
        if (empty($input['values']) || !is_array($input['values'])) {
            throw new \InvalidArgumentException("Missing or invalid values for delete");
        }

        $result['column'] = $input['column'];
        $result['values'] = $input['values'];
        $result['limit'] = isset($input['limit']) ? max(1, (int)$input['limit']) : null;

        return $result;
    }

    // ──────────────────────────────
    // 🔹 Helper Methods
    // ──────────────────────────────

    private function requireTable(?string $table): string
    {
        if (!$table) {
            throw new \InvalidArgumentException("Missing required field: table");
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException("Invalid table name");
        }
        if (
            !empty($this->config['db']['whitelist_tables']) &&
            !in_array($table, $this->config['db']['whitelist_tables'], true)
        ) {
            throw new \InvalidArgumentException("Table '{$table}' is not whitelisted");
        }
        return $table;
    }

    private function sanitizeColumns(array $columns): array
    {
        if (count($columns) === 1 && $columns[0] === '*') {
            return ['*'];
        }
        $safe = [];
        foreach ($columns as $col) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $col)) {
                throw new \InvalidArgumentException("Invalid column name: {$col}");
            }
            $safe[] = $col;
        }
        return $safe;
    }

    private function parseCondition($condition): array
    {
        if (empty($condition)) return [];

        if (is_string($condition)) {
            $decoded = json_decode($condition, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
            throw new \InvalidArgumentException("Invalid JSON format for condition");
        }

        if (is_array($condition)) {
            return $condition;
        }

        throw new \InvalidArgumentException("Condition must be JSON string or array");
    }
}
