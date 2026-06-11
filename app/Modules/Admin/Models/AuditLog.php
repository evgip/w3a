<?php

namespace App\Modules\Admin\Models;

use App\Core\Model;
use App\Core\Database;

class AuditLog extends Model
{
    protected string $table = 'audit_logs';
    protected bool $useSoftDeletes = false;

    /**
     * Поиск и фильтрация логов с постраничной навигацией
     */
    public function getFilteredLogs(int $limit, int $offset, ?int $userId, ?string $action, ?string $search): array
    {
        $db = Database::getConnection();
        
        $sql = "SELECT * FROM `audit_logs` WHERE 1=1";
        $bindings = [];

        if ($userId !== null) {
            $sql .= " AND `user_id` = :user_id";
            $bindings['user_id'] = $userId;
        }

        if ($action !== null) {
            $sql .= " AND `action` = :action";
            $bindings['action'] = $action;
        }

        if ($search !== null) {
            // В вашей таблице поля могут называться context или description/payload. 
            // Используем универсальный поиск по описанию
            $sql .= " AND (`description` LIKE :search_desc OR `action` LIKE :search_action)";
            $bindings['search_desc'] = '%' . $search . '%';
            $bindings['search_action'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY `id` DESC LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        
        // Привязываем параметры пагинации жестко как INT
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        
        // Привязываем остальные фильтры
        foreach ($bindings as $key => $val) {
            $stmt->bindValue(':' . $key, $val);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Подсчет общего количества строк с учетом текущих фильтров (для пагинации)
     */
    public function getFilteredCount(?int $userId, ?string $action, ?string $search): int
    {
        $db = Database::getConnection();
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

        if ($search !== null) {
            $sql .= " AND (`description` LIKE :search_desc OR `action` LIKE :search_action)";
            $bindings['search_desc'] = '%' . $search . '%';
            $bindings['search_action'] = '%' . $search . '%';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($bindings);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Получить список уникальных экшенов для выпадающего списка в UI
     */
    public function getUniqueActions(): array
    {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT DISTINCT `action` FROM `audit_logs` ORDER BY `action` ASC");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
