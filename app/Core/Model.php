<?php

namespace App\Core;

use PDO;

abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';
 
    /**
     * Флаг мягкого удаления (soft delete)
     * Установим в true в моделях, где есть колонка deleted_at
     */
    protected bool $softDeletes = false;
 
    // Flag to control whether to include soft-deleted records in queries
    protected bool $includeTrashed = false;

    /**
     * Chainable method to include soft-deleted records in the next query execution
     */
    public function withTrashed(): self
    {
        $this->includeTrashed = true;
        return $this;
    }

    /**
     * Helper to append the default active-records SQL filter constraint
     */
    protected function applySoftDeleteConstraint(string $sql): string
    {
        if (!$this->includeTrashed) {
            // If the query doesn't already contain a WHERE statement
            if (strpos(strtoupper($sql), 'WHERE') === false) {
                $sql .= " WHERE `deleted_at` IS NULL";
            } else {
                $sql .= " AND `deleted_at` IS NULL";
            }
        }
        
        // Reset flag configuration for subsequent queries
        $this->includeTrashed = false;
        return $sql;
    }

    /**
     * Get all active records from the table
     */
    public function all(): array
    {
        $db = Database::getConnection();
        $sql = $this->applySoftDeleteConstraint("SELECT * FROM `{$this->table}`");
        
        $stmt = $db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Найти конкретную запись по её первичному ключу ID
     */
    public function find($id): ?array
    {
        $db = Database::getConnection();
        
        // Сначала пишем базовый запрос БЕЗ LIMIT 1
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id";
        
        // Применяем фильтр мягкого удаления (он безопасно допишет AND deleted_at IS NULL)
        $sql = $this->applySoftDeleteConstraint($sql);
        
        // И только в самом конце приклеиваем LIMIT 1
        $sql .= " LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ? $result : null;
    }

    /**
     * Найти активную запись по конкретному значению колонки (например, по email)
     */
    public function findBy(string $column, $value): ?array
    {
        $db = Database::getConnection();
        
        // Базовый запрос без лимита
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$column}` = :value";
        
        // Безопасно применяем фильтр мягкого удаления
        $sql = $this->applySoftDeleteConstraint($sql);
        
        // Дописываем лимит в самый конец запроса
        $sql .= " LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute(['value' => $value]);
        $result = $stmt->fetch();
        return $result ? $result : null;
    }
	
	
    /**
     * Create a new record in the database
     */
    public function create(array $data): int
    {
        $db = Database::getConnection();
        
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO `{$this->table}` ({$columns}) VALUES ({$placeholders})";
        $stmt = $db->prepare($sql);
        $stmt->execute($data);
        
        return (int)$db->lastInsertId();
    }

    /**
     * Update an existing database record
     */
    public function update($id, array $data): bool
    {
        $db = Database::getConnection();
        
        $fields = '';
        foreach ($data as $key => $value) {
            $fields .= "`{$key}` = :{$key}, ";
        }
        $fields = rtrim($fields, ', ');
        
        $sql = "UPDATE `{$this->table}` SET {$fields} WHERE `{$this->primaryKey}` = :_id";
        $stmt = $db->prepare($sql);
        
        $data['_id'] = $id;
        return $stmt->execute($data);
    }

    /**
     * SOFT DELETE: Flags the row as deleted instead of dropping it entirely
     */
    public function delete($id): bool
    {
        // Log auditing footprint automatically if required
        \App\Core\Audit::log('model.soft_delete', "Запись отправлена в архив", [
            'table' => $this->table,
            'record_id' => $id
        ]);

        return $this->update($id, [
            'deleted_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * RESTORE: Reverses soft deletion by setting the column back to NULL
     */
    public function restore($id): bool
    {
        \App\Core\Audit::log('model.restore', "Запись восстановлена из архива", [
            'table' => $this->table,
            'record_id' => $id
        ]);

        return $this->update($id, [
            'deleted_at' => null
        ]);
    }

    /**
     * FORCE DELETE: Permanent structural destruction of the row
     */
    public function forceDelete($id): bool
    {
        \App\Core\Audit::log('model.force_delete', "Запись полностью уничтожена из БД", [
            'table' => $this->table,
            'record_id' => $id
        ]);

        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id");
        return $stmt->execute(['id' => $id]);
    }
}
