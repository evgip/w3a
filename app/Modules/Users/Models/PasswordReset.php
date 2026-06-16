<?php

namespace App\Modules\Users\Models;

use App\Core\Model;

class PasswordReset extends Model
{
    protected string $table = 'password_resets';

    /**
     * Store a cryptographically safe token signature linked to a user email
     */
    public function createToken(string $email, string $token): void
    {
        // Clear out any old pending reset records for this specific email address
        $stmt = static::db()->prepare("DELETE FROM `password_resets` WHERE `email` = :email");
        $stmt->execute(['email' => $email]);

        // Persist the clean new token
        $this->create([
            'email' => $email,
            'token' => $token
        ]);
    }

    /**
     * Validate token freshness against a 1-hour expiration sliding window
     */
    public function validateToken(string $token): ?string
    {
        // Auto-cleanup stale rows older than 1 hour with a 10% lottery chance
        if (random_int(1, 100) <= 10) {
            static::db()->query("DELETE FROM `password_resets` WHERE `created_at` < NOW() - INTERVAL 1 HOUR");
        }

        $stmt = static::db()->prepare("SELECT `email` FROM `password_resets` WHERE `token` = :token AND `created_at` >= NOW() - INTERVAL 1 HOUR LIMIT 1");
        $stmt->execute(['token' => $token]);
        $email = $stmt->fetchColumn();

        return $email !== false ? (string)$email : null;
    }

    /**
     * Purge a token completely after successful usage
     */
    public function clearToken(string $token): void
    {
        $stmt = static::db()->prepare("DELETE FROM `password_resets` WHERE `token` = :token");
        $stmt->execute(['token' => $token]);
    }
}
