<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Models\AuditLog;
use App\Core\Database;

class AdminAuditService
{
    private AuditLog $auditLogModel;
    
    public function __construct()
    {
        $this->auditLogModel = new AuditLog();
    }
    
    /**
     * Получить отфильтрованные логи аудита.
     */
    public function getFilteredLogs(
        int $perPage,
        int $offset,
        ?int $filterUserId = null,
        ?string $filterAction = null,
        ?string $searchQuery = null,
        ?string $filterCategory = null  // ✅ Новый параметр
    ): array {
        $logs = $this->auditLogModel->getFilteredLogs(
            $perPage, $offset, $filterUserId, $filterAction, $searchQuery, $filterCategory
        );
        
        // Декодируем JSON-контекст
        foreach ($logs as &$log) {
            $payloadField = $log['payload'] ?? '';
            $log['decoded_payload'] = $payloadField ? json_decode($payloadField, true) : [];
        }
        
        return $logs;
    }
    
    /**
     * Получить уникальные действия для фильтра.
     */
    public function getUniqueActions(): array
    {
        return $this->auditLogModel->getUniqueActions();
    }

    /**
     * ✅ НОВЫЙ МЕТОД: Получить уникальные категории для фильтра.
     */
    public function getUniqueCategories(): array
    {
        return $this->auditLogModel->getUniqueCategories();
    }
    
    /**
     * Получить общее количество логов с учётом фильтров.
     */
    public function getFilteredCount(
        ?int $filterUserId = null,
        ?string $filterAction = null,
        ?string $searchQuery = null,
        ?string $filterCategory = null  // ✅ Новый параметр
    ): int {
        return $this->auditLogModel->getFilteredCount(
            $filterUserId, $filterAction, $searchQuery, $filterCategory
        );
    }
    
    /**
     * Получить последние security-алерты.
     */
    public function getRecentSecurityAlerts(): array
    {
        $auditModel = new \App\Modules\Admin\Models\Audit();
        return $auditModel->getRecentSecurityAlerts();
    }
    
    /**
     * Полностью очистить таблицу логов аудита.
     */
    public function clearAuditLogs(): bool
    {
        try {
            $db = Database::getConnection();
            $db->exec("TRUNCATE TABLE `audit_logs`");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}