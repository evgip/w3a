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


    protected static ?\PDO $db = null;

    protected static function db(): \PDO
    {
        if (self::$db === null) {
            self::$db = Database::getConnection();
        }
        return self::$db;
    }

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

		if ($this->includeTrashed) {
			error_log("Result: фильтр НЕ применён");
			return $sql;
		}
		
		if (strpos(strtoupper($sql), 'WHERE') === false) {
			$sql .= " WHERE `deleted_at` IS NULL";
		} else {
			$sql .= " AND `deleted_at` IS NULL";
		}

		
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
	 * Найти запись по первичному ключу (ID).
	 *
	 * По умолчанию учитывает мягкое удаление (soft delete) — возвращает только
	 * неудалённые записи. Чтобы получить запись вместе с удалёнными,
	 * передайте $withTrashed = true.
	 *
	 * Примеры использования:
	 *
	 *   // Найти активную (неудалённую) историю по ID
	 *   $story = $storyModel->find(42);
	 *
	 *   // Найти историю по ID, даже если она была удалена (soft delete)
	 *   $story = $storyModel->find(42, withTrashed: true);
	 *
	 *   // Найти пользователя (в таблице нет deleted_at — soft delete игнорируется)
	 *   $user = $userModel->find(7);
	 *
	 * @param int|string $id           Значение первичного ключа.
	 * @param bool       $withTrashed  Если true — включает мягко удалённые записи
	 *                                 (игнорирует фильтр по deleted_at).
	 *                                 По умолчанию false.
	 *
	 * @return array|null Найденная запись в виде ассоциативного массива,
	 *                    или null, если запись не найдена.
	 *
	 * @throws \InvalidArgumentException Если $id не является числом.
	 */
	public function find(int|string $id, bool $withTrashed = false): ?array
	{
		if (!is_numeric($id)) {
			throw new \InvalidArgumentException("Invalid ID");
		}

		$db = Database::getConnection();

		// Базовый запрос по первичному ключу
		$sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id";

		// Применяем фильтр мягкого удаления, только если НЕ запрошены удалённые записи
		if (!$withTrashed) {
			$sql = $this->applySoftDeleteConstraint($sql);
		}

		$sql .= " LIMIT 1";

		$stmt = $db->prepare($sql);
		$stmt->execute(['id' => $id]);
		$result = $stmt->fetch();

		return $result ?: null;
	}

    /**
     * Найти активную запись по конкретному значению колонки (например, по email)
     */
    public function findBy(string $column, $value): ?array
    {
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
			throw new \InvalidArgumentException("Invalid column name");
		}
		
        // Базовый запрос без лимита
		$db = Database::getConnection();
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

        return $this->update($id, [
            'deleted_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * RESTORE: Reverses soft deletion by setting the column back to NULL
     */
    public function restore($id): bool
    {
        return $this->update($id, [
            'deleted_at' => null
        ]);
    }

    /**
     * FORCE DELETE: Permanent structural destruction of the row
     */
    public function forceDelete($id): bool
    {
        \App\Core\Audit::log('model.force_delete', "Запись полностью уничтожена из БД", 'model', [
            'table' => $this->table,
            'record_id' => $id
        ]);

        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id");
        return $stmt->execute(['id' => $id]);
    }
}
