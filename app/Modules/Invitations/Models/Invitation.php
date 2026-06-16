<?php

namespace App\Modules\Invitations\Models;

use App\Core\Model;

class Invitation extends Model
{
    protected string $table = 'invitations';

    /**
     * Генерация уникального кода приглашения
     */
    public static function generateCode(): string
    {
        return bin2hex(random_bytes(16)); // 32 символа
    }

    /**
     * Создать новое приглашение
     */
    public function createInvitation(int $inviterId, ?string $email = null, int $expiresDays = 7): int
    {
        $code = self::generateCode();
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresDays} days"));

        return $this->create([
            'code' => $code,
            'inviter_id' => $inviterId,
            'invitee_email' => $email,
            'status' => 'pending',
            'expires_at' => $expiresAt
        ]);
    }

    /**
     * Найти приглашение по коду
     */
    public function findByCode(string $code): ?array
    {
        return $this->findBy('code', $code);
    }

    /**
     * Проверить валидность приглашения
     */
    public function isValid(array $invitation): bool
    {
        if ($invitation['status'] !== 'pending') {
            return false;
        }

        if (strtotime($invitation['expires_at']) < time()) {
            return false;
        }

        return true;
    }

    /**
     * Активировать приглашение при регистрации
     */
    public function acceptInvitation(string $code, int $inviteeId): bool
    {
        $invitation = $this->findByCode($code);
        if (!$invitation || !$this->isValid($invitation)) {
            return false;
        }

        return $this->update((int)$invitation['id'], [
            'invitee_id' => $inviteeId,
            'status' => 'accepted'
        ]);
    }

    /**
     * Получить все приглашения пользователя
     */
    public function getUserInvitations(int $userId): array
    {
        $stmt = static::db()->prepare("
            SELECT i.*, u.username as invitee_username
            FROM invitations i
            LEFT JOIN users u ON i.invitee_id = u.id
            WHERE i.inviter_id = :user_id
            ORDER BY i.created_at DESC
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Получить активные (неиспользованные) приглашения
     */
    public function getActiveInvitations(int $userId): array
    {
        $stmt = static::db()->prepare("
            SELECT * FROM invitations
            WHERE inviter_id = :user_id
            AND status = 'pending'
            AND expires_at > NOW()
            ORDER BY created_at DESC
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Отозвать приглашение
     */
    public function revokeInvitation(int $invitationId, int $userId): bool
    {
        $invitation = $this->find($invitationId);
        if (!$invitation || (int)$invitation['inviter_id'] !== $userId) {
            return false;
        }

        if ($invitation['status'] !== 'pending') {
            return false;
        }

        return $this->update($invitationId, ['status' => 'revoked']);
    }

    /**
     * Подсчитать количество активных приглашений пользователя
     */
    public function countActiveInvitations(int $userId): int
    {
        $stmt = static::db()->prepare("
            SELECT COUNT(*) FROM invitations
            WHERE inviter_id = :user_id
            AND status = 'pending'
            AND expires_at > NOW()
        ");
        $stmt->execute(['user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Очистка просроченных приглашений
     */
    public function cleanupExpired(): int
    {
        $stmt = static::db()->prepare("
            UPDATE invitations
            SET status = 'expired'
            WHERE status = 'pending'
            AND expires_at < NOW()
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }
}