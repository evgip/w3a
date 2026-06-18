<?php
declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Models\AuditLog;
use App\Core\Database;

/**
 * Сервис для работы с журналом аудита.
 */
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
        ?string $searchQuery = null
    ): array {
        $logs = $this->auditLogModel->getFilteredLogs(
            $perPage, $offset, $filterUserId, $filterAction, $searchQuery
        );
        
        // Декодируем JSON-контекст
        foreach ($logs as &$log) {
            $payloadField = $log['payload'] ?? $log['context'] ?? '';
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
     * Получить общее количество логов с учётом фильтров.
     */
    public function getFilteredCount(
        ?int $filterUserId = null,
        ?string $filterAction = null,
        ?string $searchQuery = null
    ): int {
        return $this->auditLogModel->getFilteredCount($filterUserId, $filterAction, $searchQuery);
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