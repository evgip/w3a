<?php

namespace App\Core;

use PDO;
use InvalidArgumentException;
use RuntimeException;

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

    /**
     * Флаг для включения мягко удалённых записей в запросы
     */
    protected bool $includeTrashed = false;

    /**
     * @var Database Экземпляр Database для работы с БД
     */
    protected Database $db;

    /**
     * @var Logger|null Экземпляр Logger для логирования
     */
    protected ?Logger $logger;

    /**
     * Конструктор модели с инъекцией зависимостей
     * 
     * @param Database $db Экземпляр Database
     * @param Logger|null $logger Экземпляр Logger (опционально)
     */
    public function __construct(Database $db, ?Logger $logger = null)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Цепочный метод для включения мягко удалённых записей в следующий запрос
     */
    public function withTrashed(): self
    {
        $this->includeTrashed = true;
        return $this;
    }

	/**
	 * Вспомогательный метод для добавления SQL-фильтра мягкого удаления
	 * Учитывает вложенность скобок и строковые литералы
	 */
	protected function applySoftDeleteConstraint(string $sql): string
	{
		if ($this->includeTrashed) {
			$this->includeTrashed = false;
			return $sql;
		}
		
		// Проверяем, есть ли WHERE на верхнем уровне (вне скобок)
		$hasTopLevelWhere = $this->hasTopLevelKeyword($sql, 'WHERE');
		
		if ($hasTopLevelWhere) {
			$sql .= " AND `deleted_at` IS NULL";
		} else {
			$sql .= " WHERE `deleted_at` IS NULL";
		}
		
		return $sql;
	}

	/**
	 * Проверяет наличие ключевого слова на верхнем уровне SQL (вне скобок и строк)
	 */
	private function hasTopLevelKeyword(string $sql, string $keyword): bool
	{
		$sql = strtoupper($sql);
		$keyword = strtoupper($keyword);
		$keywordLength = strlen($keyword);
		
		$parenDepth = 0;      // Глубина вложенности скобок
		$inString = false;    // Внутри строкового литерала
		$stringChar = '';     // Тип кавычки (' или ")
		$escaped = false;     // Предыдущий символ был escape
		
		$length = strlen($sql);
		
		for ($i = 0; $i < $length; $i++) {
			$char = $sql[$i];
			
			// Обработка escape-символов
			if ($escaped) {
				$escaped = false;
				continue;
			}
			
			if ($char === '\\') {
				$escaped = true;
				continue;
			}
			
			// Обработка строковых литералов
			if ($inString) {
				if ($char === $stringChar) {
					$inString = false;
				}
				continue;
			}
			
			// Начало строкового литерала
			if ($char === '\'' || $char === '"') {
				$inString = true;
				$stringChar = $char;
				continue;
			}
			
			// Обработка скобок
			if ($char === '(') {
				$parenDepth++;
				continue;
			}
			
			if ($char === ')') {
				$parenDepth--;
				continue;
			}
			
			// Проверяем ключевое слово только на верхнем уровне
			if ($parenDepth === 0) {
				// Проверяем совпадение ключевого слова
				if (substr($sql, $i, $keywordLength) === $keyword) {
					// Проверяем границы слова (не часть другого слова)
					$before = ($i > 0) ? $sql[$i - 1] : ' ';
					$after = ($i + $keywordLength < $length) ? $sql[$i + $keywordLength] : ' ';
					
					if (!ctype_alnum($before) && $before !== '_' && 
						!ctype_alnum($after) && $after !== '_') {
						return true;
					}
				}
			}
		}
		
		return false;
	}

    /**
     * Получить все активные записи из таблицы
     */
    public function all(): array
    {
        $sql = $this->applySoftDeleteConstraint("SELECT * FROM `{$this->table}`");
        return $this->db->fetchAll($sql);
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
     * @throws InvalidArgumentException Если $id не является числом.
     */
    public function find(int|string $id, bool $withTrashed = false): ?array
    {
        if (!is_numeric($id)) {
            throw new InvalidArgumentException("Invalid ID");
        }

        // Базовый запрос по первичному ключу
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id";

        // Применяем фильтр мягкого удаления, только если НЕ запрошены удалённые записи
        if (!$withTrashed) {
            $sql = $this->applySoftDeleteConstraint($sql);
        }

        $sql .= " LIMIT 1";

        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Найти активную запись по конкретному значению колонки (например, по email)
     */
    public function findBy(string $column, mixed $value): ?array
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new InvalidArgumentException("Invalid column name");
        }
        
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$column}` = :value";
        $sql = $this->applySoftDeleteConstraint($sql);
        $sql .= " LIMIT 1";

        return $this->db->fetchOne($sql, ['value' => $value]);
    }

    /**
     * Фильтрует входящие данные, оставляя только разрешённые поля.
     * + логирование в случае подбора
     * 
     * @param array $data Исходные данные
     * @return array Отфильтрованные данные
     * @throws RuntimeException Если $fillable не определён в модели
     */
    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            throw new RuntimeException("Модель '" . static::class . "' должна определять свойство \$fillable.");
        }

        $allowedKeys = array_flip($this->fillable);
        $filteredData = array_intersect_key($data, $allowedKeys);

        // Находим поля, которые были в запросе, но не разрешены
        $rejectedKeys = array_diff_key($data, $allowedKeys);

        if (!empty($rejectedKeys) && $this->logger !== null) {
            // ✅ Логируем попытку массового назначения запрещённых полей
            $keysString = implode(', ', array_keys($rejectedKeys));
            $this->logger->error("Mass Assignment Attempt", [
                'model' => static::class,
                'rejected_fields' => $keysString,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }

        return $filteredData;
    }


    /**
     * Создать новую запись в базе данных
     * 
     * @param array $data Ассоциативный массив column => value
     * @return int ID созданной записи
     * @throws InvalidArgumentException Если не предоставлены валидные данные
     */
    public function create(array $data): int
    {
        // 1. Фильтрация по белому списку
        $data = $this->filterFillable($data);

        if (empty($data)) {
            throw new InvalidArgumentException("Нет разрешённых полей для создания записи.");
        }

        // 2. Экранирование имён колонок обратными кавычками (защита в глубину)
        $columns = '`' . implode('`, `', array_keys($data)) . '`';
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO `{$this->table}` ({$columns}) VALUES ({$placeholders})";
        
        $this->db->query($sql, $data);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Обновить существующую запись в базе данных
     * 
     * @param int|string $id ID записи для обновления
     * @param array $data Ассоциативный массив column => value для обновления
     * @return bool True при успехе, false при неудаче
     * @throws InvalidArgumentException Если не предоставлены валидные данные
     */
    public function update(int|string $id, array $data): bool
    {
        // 1. Фильтрация по белому списку
        $data = $this->filterFillable($data);

        if (empty($data)) {
            throw new InvalidArgumentException("Нет разрешённых полей для обновления записи.");
        }

        $fields = '';
        foreach ($data as $key => $value) {
            $fields .= "`{$key}` = :{$key}, ";
        }
        $fields = rtrim($fields, ', ');

        $sql = "UPDATE `{$this->table}` SET {$fields} WHERE `{$this->primaryKey}` = :_id";
        
        $data['_id'] = $id;
        
        return $this->db->execute($sql, $data) > 0;
    }

    /**
     * SOFT DELETE: Помечает запись как удалённую вместо полного удаления
     */
    public function delete(int|string $id): bool
    {
        if (!is_numeric($id)) {
            throw new InvalidArgumentException("Invalid ID");
        }

        return $this->update($id, [
            'deleted_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * RESTORE: Отменяет мягкое удаление, устанавливая колонку обратно в NULL
     */
    public function restore(int|string $id): bool
    {
        return $this->update($id, [
            'deleted_at' => null
        ]);
    }

    /**
     * FORCE DELETE: Полное структурное уничтожение записи
     */
    public function forceDelete(int|string $id): bool
    {
        if (!is_numeric($id)) {
            throw new InvalidArgumentException("Invalid ID");
        }

        if ($this->logger !== null) {
            $this->logger->warning('Model force delete', [
                'table' => $this->table,
                'record_id' => $id,
                'model' => static::class,
            ]);
        }

        $sql = "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id";
        
        return $this->db->execute($sql, ['id' => $id]) > 0;
    }

    /**
     * Получить количество записей с опциональным условием
     * 
     * @param string $where Опциональное WHERE условие (без ключевого слова WHERE)
     * @param array $params Параметры для запроса
     * @return int Количество записей
     */
    public function count(string $where = '', array $params = []): int
    {
        $sql = "SELECT COUNT(*) as count FROM `{$this->table}`";
        
        if (!empty($where)) {
            $sql .= " WHERE " . $where;
        }
        
        $sql = $this->applySoftDeleteConstraint($sql);
        
        $result = $this->db->fetchOne($sql, $params);
        
        return (int)($result['count'] ?? 0);
    }

    /**
     * Найти записи с опциональными условиями, сортировкой и лимитом
     * 
     * @param string $where Опциональное WHERE условие
     * @param array $params Параметры для запроса
     * @param string $orderBy Опциональная сортировка (например, "created_at DESC")
     * @param int|null $limit Опциональный лимит записей
     * @param int|null $offset Опциональное смещение
     * @return array Массив записей
     */
    public function where(
        string $where, 
        array $params = [], 
        string $orderBy = '', 
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $sql = "SELECT * FROM `{$this->table}` WHERE " . $where;
        $sql = $this->applySoftDeleteConstraint($sql);
        
        if (!empty($orderBy)) {
            $sql .= " ORDER BY " . $orderBy;
        }
        
        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        if ($offset !== null) {
            $sql .= " OFFSET " . (int)$offset;
        }
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Получить экземпляр Database
     */
    public function getDatabase(): Database
    {
        return $this->db;
    }

    /**
     * Установить экземпляр Database (для тестирования)
     */
    public function setDatabase(Database $db): void
    {
        $this->db = $db;
    }

    /**
     * Установить экземпляр Logger
     */
    public function setLogger(Logger $logger): void
    {
        $this->logger = $logger;
    }
}