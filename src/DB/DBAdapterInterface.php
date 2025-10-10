<?php

namespace FlexiAPI\DB;

interface DBAdapterInterface
{
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): int;
    public function prepare(string $sql);
    public function beginTransaction(): void;
    public function commit(): void;
    public function rollBack(): void;
    public function isTableAllowed(string $table): bool;
    public function lastInsertId(): string;
}
