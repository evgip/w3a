<?php

namespace App\Modules\Users\Models;

use App\Core\Model;
use App\Core\Database;

class EmailActivation extends Model
{
    protected string $table = 'email_activations';

	protected array $fillable = [
		'user_id',
		'email_activations',
		'token'
	];

    /**
     * Allocate and persist a unique activation token token for a user
     */
    public function createActivationToken(int $userId): string
    {
        $db = Database::getConnection();
        
        // Wipe any stale pending activation rows for this user id
        $stmt = $db->prepare("DELETE FROM `email_activations` WHERE `user_id` = :uid");
        $stmt->execute(['uid' => $userId]);

        $token = bin2hex(random_bytes(32));
        
        $this->create([
            'user_id' => $userId,
            'token'   => $token
        ]);

        return $token;
    }

    /**
     * Validate token existence and return the associated user ID
     */
    public function verifyToken(string $token): ?int
    {
        $db = Database::getConnection();
        
        // Optional: Enforce a 24-hour expiration window on activation sequences
        $stmt = $db->prepare("SELECT `user_id` FROM `email_activations` WHERE `token` = :token LIMIT 1");
        $stmt->execute(['token' => $token]);
        $userId = $stmt->fetchColumn();

        return $userId !== false ? (int)$userId : null;
    }

    /**
     * Purge the token row after successful activation handling
     */
    public function clearToken(string $token): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM `email_activations` WHERE `token` = :token");
        $stmt->execute(['token' => $token]);
    }
}
