<?php

namespace App\Core;

use App\Modules\Errors\Controllers\ErrorsController;

class Firewall
{
    /**
     * Intercept incoming connections and check against the database IP blacklists matrix
     */
    public static function check(): void
    {
        $ip = IpResolver::getClientIp();
        $db = Database::getConnection();

        // High-performance single lookup statement index scan
        $stmt = $db->prepare("SELECT `reason` FROM `banned_ips` WHERE `ip_address` = :ip LIMIT 1");
        $stmt->execute(['ip' => $ip]);
        $reason = $stmt->fetchColumn();

        if ($reason !== false) {
            $controller = new ErrorsController();
            $controller->forbidden("Ваш IP-адрес заблокирован. Причина: " . $reason);
            exit;
        }
    }
}
