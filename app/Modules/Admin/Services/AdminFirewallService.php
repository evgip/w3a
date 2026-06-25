<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Core\Database;
use App\Core\Audit;

/**
 * Сервис для управления IP-баном (Firewall).
 */
class AdminFirewallService
{
    /**
     * Получить список заблокированных IP.
     */
    public function getBannedIps(): array
    {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM `banned_ips` ORDER BY id DESC");
        return $stmt->fetchAll();
    }

    /**
     * Заблокировать IP-адрес.
     *
     * @return bool true если успешно, false если уже заблокирован
     */
    public function banIp(string $ip, string $reason = 'Нарушение правил сообщества'): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        $db = Database::getConnection();

        try {
            $stmt = $db->prepare("INSERT INTO `banned_ips` (`ip_address`, `reason`) VALUES (:ip, :reason)");
            $stmt->execute(['ip' => $ip, 'reason' => $reason]);

            Audit::log('admin.ip_banned', "Администратор заблокировал IP: {$ip}", 'admin');

            return true;
        } catch (\Exception $e) {
            return false; // IP уже заблокирован
        }
    }

    /**
     * Разблокировать IP-адрес.
     */
    public function unbanIp(int $id): ?string
    {
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT `ip_address` FROM `banned_ips` WHERE `id` = :id");
        $stmt->execute(['id' => $id]);
        $ip = $stmt->fetchColumn();

        if (!$ip) {
            return null;
        }

        $stmt = $db->prepare("DELETE FROM `banned_ips` WHERE `id` = :id");
        $stmt->execute(['id' => $id]);

        Audit::log('admin.ip_unbanned', "Администратор разблокировал IP: {$ip}", 'admin');

        return $ip;
    }
}
