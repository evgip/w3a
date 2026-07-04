<?php

namespace App\Core\Contracts;

interface DatabaseInterface
{
    public function query(string $sql, array $params = []): \PDOStatement;
    public function execute(string $sql, array $params = []): int;
    public function fetchOne(string $sql, array $params = []): ?array;
    public function fetchAll(string $sql, array $params = []): array;
    public function fetchColumn(string $sql, array $params = [], int $column = 0): mixed;
    public function beginTransaction(): bool;
    public function commit(): bool;
    public function rollBack(): bool;
    public function lastInsertId(): string;
}