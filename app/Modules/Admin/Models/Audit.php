<?php

namespace App\Modules\Admin\Models;

use App\Core\Model;
use App\Core\Database;

class Audit extends Model
{
    protected string $table = 'audit_logs';

    /**
     * Pulls critical security alert events logged within the last 5 minutes
     * 
     * @return array
     */
    public function getRecentSecurityAlerts(): array
    {
        $db = Database::getConnection();
        
        // Highly optimized index scan locating specific operational severity markers
        $sql = "SELECT id, user_id, action, description, ip_address, created_at 
                FROM `audit_logs` 
                WHERE `action` IN ('security.csrf_failed', 'security.rate_limited', 'auth.failed_bruteforce')
                  AND `created_at` >= NOW() - INTERVAL 5 MINUTE
                ORDER BY id DESC LIMIT 10";

        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
