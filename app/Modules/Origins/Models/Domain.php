<?php

namespace App\Modules\Origins\Models;

use App\Core\Model;

class Domain extends Model
{
    protected string $table = 'domains';

    protected array $fillable = [
        'domain',
        'status',
        'ban_reason',
        'banned_by',
        'deleted_at',
    ];

    /**
     * Проверить, забанен ли домен
     */
    public function isBanned(string $domain): bool
    {
        $domain = strtolower(trim($domain));
        if (empty($domain)) {
            return false;
        }

        $stmt = static::db()->prepare(
            "SELECT COUNT(*) FROM `{$this->table}`
             WHERE LOWER(`domain`) = :domain
               AND `status` = 'banned'
               AND `deleted_at` IS NULL"
        );
        $stmt->execute(['domain' => $domain]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Получить информацию о бане домена (если забанен)
     */
    public function getBanInfo(string $domain): ?array
    {
        $domain = strtolower(trim($domain));
        if (empty($domain)) {
            return null;
        }

        $stmt = static::db()->prepare(
            "SELECT d.*, u.username AS banned_by_name
             FROM `{$this->table}` d
             LEFT JOIN `users` u ON d.banned_by = u.id
             WHERE LOWER(d.`domain`) = :domain
               AND d.`status` = 'banned'
               AND d.`deleted_at` IS NULL
             LIMIT 1"
        );
        $stmt->execute(['domain' => $domain]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Забанить домен
     */
    public function ban(string $domain, string $reason, int $bannedBy): bool
    {
        $domain = strtolower(trim($domain));

        // Проверяем, не забанен ли уже
        if ($this->isBanned($domain)) {
            return false;
        }

        // Проверяем, существует ли запись (возможно, была ранее разбанена)
        $existing = $this->findBy('domain', $domain);

        if ($existing) {
            // Восстанавливаем и обновляем
            $this->update($existing['id'], [
                'status'     => 'banned',
                'ban_reason' => $reason,
                'banned_by'  => $bannedBy,
                'deleted_at' => null,
            ]);
            return true;
        }

        $this->create([
            'domain'     => $domain,
            'status'     => 'banned',
            'ban_reason' => $reason,
            'banned_by'  => $bannedBy,
        ]);
        return true;
    }

    /**
     * Разбанить домен (мягкое удаление)
     */
    public function unban(string $domain): bool
    {
        $domain = strtolower(trim($domain));

        $stmt = static::db()->prepare(
            "UPDATE `{$this->table}`
             SET `status` = 'allowed', `deleted_at` = NOW()
             WHERE LOWER(`domain`) = :domain
               AND `deleted_at` IS NULL"
        );
        return $stmt->execute(['domain' => $domain]);
    }

    /**
     * Получить список всех забаненных доменов с информацией о модераторе
     */
    public function getBannedDomains(): array
    {
        $stmt = static::db()->query(
            "SELECT d.*, u.username AS banned_by_name
             FROM `{$this->table}` d
             LEFT JOIN `users` u ON d.banned_by = u.id
             WHERE d.`status` = 'banned'
               AND d.`deleted_at` IS NULL
             ORDER BY d.created_at DESC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Получить все домены (включая разрешённые) — для админки
     */
    public function getAllDomains(): array
    {
        $stmt = static::db()->query(
            "SELECT d.*, u.username AS banned_by_name
             FROM `{$this->table}` d
             LEFT JOIN `users` u ON d.banned_by = u.id
             WHERE d.`deleted_at` IS NULL
             ORDER BY d.created_at DESC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Подсчитать количество забаненных доменов
     */
    public function getBannedCount(): int
    {
        $stmt = static::db()->query(
            "SELECT COUNT(*) FROM `{$this->table}`
             WHERE `status` = 'banned' AND `deleted_at` IS NULL"
        );
        return (int) $stmt->fetchColumn();
    }
}