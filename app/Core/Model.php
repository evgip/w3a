<?php

namespace App\Core;

use PDO;

abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';
    
	/**
     * Белый список полей, разрешённых для массового назначения (Mass Assignment).
     * Должен быть переопределён в дочерних моделях.
     * 
     * @var array
     */
    protected array $fillable = [];
	
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
		if (!is_numeric($id)) {
			throw new \InvalidArgumentException("Invalid ID");
		}
		
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
     * Фильтрует входящие данные, оставляя только разрешённые поля.
	 * + логирование в случае подбора
     * 
     * @param array $data Исходные данные
     * @return array Отфильтрованные данные
     * @throws \RuntimeException Если $fillable не определён в модели
     */
	protected function filterFillable(array $data): array
	{
		if (empty($this->fillable)) {
			throw new \RuntimeException("Модель '" . static::class . "' должна определять свойство \$fillable.");
		}

		$allowedKeys = array_flip($this->fillable);
		$filteredData = array_intersect_key($data, $allowedKeys);
		
		// Находим поля, которые были в запросе, но не разрешены
		$rejectedKeys = array_diff_key($data, $allowedKeys);
		
		if (!empty($rejectedKeys)) {
			// Логируем попытку массового назначения запрещённых полей
			$keysString = implode(', ', array_keys($rejectedKeys));
			\App\Core\Logger::error("Mass Assignment Attempt", [
				'model' => static::class,
				'rejected_fields' => $keysString,
				'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
			]);
		}

		return $filteredData;
	}
	
    /**
     * Create a new record in the database
     * 
     * @param array $data Associative array of column => value pairs
     * @return int The ID of the newly created record
     * @throws \InvalidArgumentException If no valid data is provided
     */
    public function create(array $data): int
    {
        // 1. Фильтрация по белому списку
        $data = $this->filterFillable($data);
        
        if (empty($data)) {
            throw new \InvalidArgumentException("Нет разрешённых полей для создания записи.");
        }
        
        $db = Database::getConnection();
        
        // 2. Экранирование имён колонок обратными кавычками (защита в глубину)
        $columns = '`' . implode('`, `', array_keys($data)) . '`';
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO `{$this->table}` ({$columns}) VALUES ({$placeholders})";
        $stmt = $db->prepare($sql);
        $stmt->execute($data);
        
        return (int)$db->lastInsertId();
    }

   /**
     * Update an existing database record
     * 
     * @param int|string $id The ID of the record to update
     * @param array $data Associative array of column => value pairs to update
     * @return bool True on success, false on failure
     * @throws \InvalidArgumentException If no valid data is provided
     */
    public function update($id, array $data): bool
    {
        // 1. Фильтрация по белому списку
        $data = $this->filterFillable($data);
        
        if (empty($data)) {
            throw new \InvalidArgumentException("Нет разрешённых полей для обновления записи.");
        }
        
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
		if (!is_numeric($id)) {
			throw new \InvalidArgumentException("Invalid ID");
		}
				
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
