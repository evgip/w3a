<?php

namespace App\Modules\Users\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\Logger;

class RateLimit extends Model
{
    protected string $table = 'rate_limits';

    protected array $fillable = [
        'ip_address',
        'endpoint_action',
        'request_count',
        'window_start'
    ];

    /**
     * Delete stale rows older than the specified sliding time window
     */
    public function clearStaleLogs(int $windowSeconds): void
    {
        // ✅ Используем prepare() для bindValue с типом PDO::PARAM_INT
        $stmt = $this->db->prepare(
            "DELETE FROM `rate_limits` WHERE `created_at` < NOW() - INTERVAL :win SECOND"
        );
        $stmt->bindValue(':win', $windowSeconds, \PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Compute total requests logged by an IP footprint within a sliding window frame
     */
    public function getRequestCount(string $ip, string $action, int $windowSeconds): int
    {
        $sql = "SELECT COUNT(*) FROM `rate_limits` 
                WHERE `ip_address` = :ip 
                  AND `endpoint_action` = :action 
                  AND `created_at` >= NOW() - INTERVAL :win SECOND";
                  
        // ✅ Используем prepare() для bindValue с разными типами
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':ip', $ip, \PDO::PARAM_STR);
        $stmt->bindValue(':action', $action, \PDO::PARAM_STR);
        $stmt->bindValue(':win', $windowSeconds, \PDO::PARAM_INT);
        $stmt->execute();
        
        return (int)$stmt->fetchColumn();
    }

    /**
     * Log a fresh request atom fingerprint tracking stamp
     */
    public function logRequest(string $ip, string $action): void
    {
        $this->create([
            'ip_address' => $ip,
            'endpoint_action' => $action
        ]);
    }
}