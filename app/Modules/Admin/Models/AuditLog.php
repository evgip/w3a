<?php

namespace App\Modules\Admin\Models;

use App\Core\Model;

class AuditLog extends Model
{
    protected string $table = 'audit_logs';
    protected bool $useSoftDeletes = false;

    /**
     * Поиск и фильтрация логов с постраничной навигацией
     */
	public function getFilteredLogs(
		int $limit,
		int $offset,
		?int $userId,
		?string $action,
		?string $search,
		?string $category = null
	): array {
		$sql = "SELECT * FROM `audit_logs` WHERE 1=1";
		$bindings = [];

		if ($userId !== null) {
			$sql .= " AND `user_id` = :user_id";
			$bindings[':user_id'] = $userId;
		}

		if ($action !== null) {
			$sql .= " AND `action` = :action";
			$bindings[':action'] = $action;
		}

		if ($category !== null && $category !== '') {
			$sql .= " AND `category` = :category";
			$bindings[':category'] = $category;
		}

		if ($search !== null) {
			$sql .= " AND (`description` LIKE :search_desc OR `action` LIKE :search_action OR `username` LIKE :search_username)";
			$bindings[':search_desc'] = '%' . $search . '%';
			$bindings[':search_action'] = '%' . $search . '%';
			$bindings[':search_username'] = '%' . $search . '%';
		}

		$limit = (int)$limit;
		$offset = (int)$offset;
		$sql .= " ORDER BY `id` DESC LIMIT {$limit} OFFSET {$offset}";

		$stmt = static::db()->prepare($sql);
		$stmt->execute($bindings);
		
		return $stmt->fetchAll();
	}

    /**
     * Подсчет общего количества строк с учетом текущих фильтров (для пагинации)
     */
    public function getFilteredCount(
        ?int $userId,
        ?string $action,
        ?string $search,
        ?string $category = null
    ): int {
        $sql = "SELECT COUNT(*) FROM `audit_logs` WHERE 1=1";
        $bindings = [];

        if ($userId !== null) {
            $sql .= " AND `user_id` = :user_id";
            $bindings['user_id'] = $userId;
        }

        if ($action !== null) {
            $sql .= " AND `action` = :action";
            $bindings['action'] = $action;
        }

        if ($category !== null && $category !== '') {
            $sql .= " AND `category` = :category";
            $bindings['category'] = $category;
        }

        if ($search !== null) {
            $sql .= " AND (`description` LIKE :search_desc OR `action` LIKE :search_action OR `username` LIKE :search_username)";
            $bindings['search_desc'] = '%' . $search . '%';
            $bindings['search_action'] = '%' . $search . '%';
            $bindings['search_username'] = '%' . $search . '%';
        }

        $stmt = static::db()->prepare($sql);
        $stmt->execute($bindings);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Получить список уникальных экшенов для выпадающего списка в UI
     */
    public function getUniqueActions(): array
    {
        $stmt = static::db()->query("SELECT DISTINCT `action` FROM `audit_logs` ORDER BY `action` ASC");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Получить список уникальных категорий
     */
    public function getUniqueCategories(): array
    {
        $stmt = static::db()->query(
            "SELECT DISTINCT `category` FROM `audit_logs` ORDER BY `category` ASC"
        );
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Получить записи по категории (для модераторского раздела)
     */
	public function getByCategory(string $category, int $limit = 50, int $offset = 0): array
	{
		$limit = (int)$limit;
		$offset = (int)$offset;
		
		$stmt = static::db()->prepare(
			"SELECT * FROM `audit_logs` 
			 WHERE `category` = :category 
			 ORDER BY `id` DESC 
			 LIMIT {$limit} OFFSET {$offset}"
		);
		$stmt->execute([':category' => $category]);
		
		return $stmt->fetchAll();
	}

    /**
     * Подсчёт записей по категории
     */
    public function countByCategory(string $category): int
    {
        $stmt = static::db()->prepare(
            "SELECT COUNT(*) FROM `audit_logs` WHERE `category` = :category"
        );
        $stmt->bindValue(':category', $category);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
}