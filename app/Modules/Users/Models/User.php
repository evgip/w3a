<?php

namespace App\Modules\Users\Models;

use App\Core\Model;

class User extends Model
{
    protected string $table = 'users';

    // Разрешаем изменять только эти поля через массовое присваивание
    protected array $fillable = [
        'username',
        'email',
        'password',
        'role',
        'is_active'
    ];

    /**
     * Получить статистику активности пользователя
     */
    public function getProfileStats(int $userId): array
    {
        $storiesStmt = static::db()->prepare("SELECT COUNT(*) FROM `stories` WHERE `user_id` = :uid AND `deleted_at` IS NULL");
        $storiesStmt->execute(['uid' => $userId]);
        $storiesCount = (int)$storiesStmt->fetchColumn();

        $commentsStmt = static::db()->prepare("SELECT COUNT(*) FROM `comments` WHERE `user_id` = :uid AND `deleted_at` IS NULL");
        $commentsStmt->execute(['uid' => $userId]);
        $commentsCount = (int)$commentsStmt->fetchColumn();

        return [
            'stories_count'  => $storiesCount,
            'comments_count' => $commentsCount
        ];
    }
    
    /**
     * Вычислить суммарную карму пользователя
     */
    public function getUserKarma(int $userId): int
    {
        $storyStmt = static::db()->prepare("SELECT SUM(`score`) FROM `stories` WHERE `user_id` = :uid AND `deleted_at` IS NULL");
        $storyStmt->execute(['uid' => $userId]);
        $storyKarma = (int)$storyStmt->fetchColumn();

        $commentStmt = static::db()->prepare("SELECT SUM(`score`) FROM `comments` WHERE `user_id` = :uid AND `deleted_at` IS NULL");
        $commentStmt->execute(['uid' => $userId]);
        $commentKarma = (int)$commentStmt->fetchColumn();

        return $storyKarma + $commentKarma;
    }
    
    /**
     * Находит пользователя по имени (username).
     */
    public function findByName(string $username): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT id, username FROM `{$this->table}` 
            WHERE username = :username AND deleted_at IS NULL
        ");
        $stmt->execute(['username' => $username]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    // ==========================================
    // Методы для работы с user_profiles
    // ==========================================
    
    public function getProfile(int $userId): ?array
    {
        $stmt = static::db()->prepare("SELECT * FROM `user_profiles` WHERE `user_id` = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function updateProfile(int $userId, array $data): bool
    {
        $fields = [];
        $params = ['user_id' => $userId];
        $allowed = ['bio', 'avatar'];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                $fields[] = "`$key` = :$key";
                $params[$key] = $value;
            }
        }
        if (empty($fields)) return false;
        
        $exists = $this->getProfile($userId);
        if ($exists) {
            $sql = "UPDATE `user_profiles` SET " . implode(', ', $fields) . " WHERE `user_id` = :user_id";
        } else {
            $fields[] = "`user_id` = :user_id";
            $sql = "INSERT INTO `user_profiles` SET " . implode(', ', $fields);
        }
        
        $stmt = static::db()->prepare($sql);
        return $stmt->execute($params);
    }

    // ==========================================
    // Методы для работы с user_settings
    // ==========================================
    
    public function getSettings(int $userId): ?array
    {
        $stmt = static::db()->prepare("SELECT * FROM `user_settings` WHERE `user_id` = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function updateSettings(int $userId, array $data): bool
    {
        $fields = [];
        $params = ['user_id' => $userId];
        $allowed = ['notify_on_reply', 'notify_on_story_comment', 'email_notifications'];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                $fields[] = "`$key` = :$key";
                $params[$key] = $value;
            }
        }
        if (empty($fields)) return false;
        
        $exists = $this->getSettings($userId);
        if ($exists) {
            $sql = "UPDATE `user_settings` SET " . implode(', ', $fields) . " WHERE `user_id` = :user_id";
        } else {
            $fields[] = "`user_id` = :user_id";
            $sql = "INSERT INTO `user_settings` SET " . implode(', ', $fields);
        }
        
        $stmt = static::db()->prepare($sql);
        return $stmt->execute($params);
    }

    // ==========================================
    // Методы для работы с user_bans
    // ==========================================

    /**
     * Проверяет, забанен ли пользователь (активен ли бан).
     */
    public function isBanned(int $userId): bool
    {
        $stmt = static::db()->prepare("
            SELECT id FROM `user_bans` 
            WHERE user_id = :id 
              AND unbanned_at IS NULL 
              AND (expires_at IS NULL OR expires_at > NOW())
            LIMIT 1
        ");
        $stmt->execute(['id' => $userId]);
        return (bool)$stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Получает информацию об активном бане пользователя.
     */
    public function getBanInfo(int $userId): ?array
    {
        $stmt = static::db()->prepare("
            SELECT * FROM `user_bans` 
            WHERE user_id = :id 
              AND unbanned_at IS NULL 
              AND (expires_at IS NULL OR expires_at > NOW())
            LIMIT 1
        ");
        $stmt->execute(['id' => $userId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    public function banUser(int $userId, ?int $bannedBy, ?string $reason, ?string $expiresAt = null): bool
    {
        $stmt = static::db()->prepare("
            INSERT INTO `user_bans` (`user_id`, `banned_by`, `reason`, `created_at`, `expires_at`) 
            VALUES (:user_id, :banned_by, :reason, NOW(), :expires_at)
        ");
        return $stmt->execute([
            'user_id' => $userId,
            'banned_by' => $bannedBy,
            'reason' => $reason,
            'expires_at' => $expiresAt
        ]);
    }

    public function unbanUser(int $userId, ?int $unbannedBy): bool
    {
        $stmt = static::db()->prepare("
            UPDATE `user_bans` 
            SET `unbanned_at` = NOW(), `unbanned_by` = :unbanned_by 
            WHERE `user_id` = :user_id AND `unbanned_at` IS NULL
        ");
        return $stmt->execute([
            'user_id' => $userId,
            'unbanned_by' => $unbannedBy
        ]);
    }
}