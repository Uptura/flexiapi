<?php

namespace FlexiAPI\Controllers;

use FlexiAPI\DB\DBAdapterInterface;
use FlexiAPI\Utils\Response;

abstract class BaseController
{
    protected DBAdapterInterface $db;
    protected array $config;

    public function __construct(DBAdapterInterface $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Ensure table is whitelisted before queries.
     */
    protected function checkTable(string $table): void
    {
        if (!$this->db->isTableAllowed($table)) {
            Response::json(false, "Table '{$table}' is not allowed.", null, 400);
        }
    }

    /**
     * Validate column names against a safe pattern (prevent injection).
     */
    protected function validateColumns(array $columns): array
    {
        $safe = [];
        foreach ($columns as $col) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $col)) {
                Response::json(false, "Invalid column name: {$col}", null, 400);
            }
            $safe[] = $col;
        }
        return $safe;
    }

    /**
     * Shortcut for returning JSON success.
     */
    protected function success(string $message, $data = null, array $extra = []): void
    {
        Response::json(true, $message, $data, 200, $extra);
    }

    /**
     * Shortcut for returning JSON error.
     */
    protected function error(string $message, int $code = 400): void
    {
        Response::json(false, $message, null, $code);
    }
}
