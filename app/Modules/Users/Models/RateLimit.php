<?php

namespace App\Modules\Users\Models;

use App\Core\Model;
use App\Core\Database;

class RateLimit extends Model
{
    protected string $table = 'rate_limits';

	protected array $fillable = [
		'ip_address',
		'endpoint_action', // <-- Добавили это поле
		'request_count',
		'window_start'
	];

    /**
     * Delete stale rows older than the specified sliding time window
     */
    public function clearStaleLogs(int $windowSeconds): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM `rate_limits` WHERE `created_at` < NOW() - INTERVAL :win SECOND");
        $stmt->bindValue(':win', $windowSeconds, \PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Compute total requests logged by an IP footprint within a sliding window frame
     */
    public function getRequestCount(string $ip, string $action, int $windowSeconds): int
    {
        $db = Database::getConnection();
        
        $sql = "SELECT COUNT(*) FROM `rate_limits` 
                WHERE `ip_address` = :ip 
                  AND `endpoint_action` = :action 
                  AND `created_at` >= NOW() - INTERVAL :win SECOND";
                  
        $stmt = $db->prepare($sql);
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
