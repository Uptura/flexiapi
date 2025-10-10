<?php

namespace FlexiAPI\DB;

use PDO;
use PDOException;
use FlexiAPI\Utils\Response;

class MySQLAdapter implements DBAdapterInterface
{
    private PDO $pdo;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";

        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            // ✅ Return clean JSON instead of fatal error
            echo Response::json(false, 'Database connection failed', null, 500, [
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function prepare(string $sql)
    {
        return $this->pdo->prepare($sql);
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    public function isTableAllowed(string $table): bool
    {
        return in_array($table, $this->config['whitelist_tables'] ?? []);
    }

    // ✅ Implement lastInsertId() from the interface
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }
}
