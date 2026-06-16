<?php

namespace App\Modules\Invitations\Models;

use App\Core\Model;

class InvitationRequest extends Model
{
    protected string $table = 'invitation_requests';

    /**
     * Создать запрос на приглашение
     */
    public function createRequest(string $email, string $reason, string $ip): int
    {
        return $this->create([
            'email' => $email,
            'reason' => $reason,
            'ip_address' => $ip,
            'status' => 'pending'
        ]);
    }

    /**
     * Проверить, есть ли уже запрос с таким email
     */
    public function hasPendingRequest(string $email): bool
    {
        $stmt = static::db()->prepare("
            SELECT COUNT(*) FROM invitation_requests
            WHERE email = :email AND status = 'pending'
        ");
        $stmt->execute(['email' => $email]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Получить все запросы (для админки)
     */
    public function getAllRequests(string $status = 'pending'): array
    {
        $stmt = static::db()->prepare("
            SELECT * FROM invitation_requests
            WHERE status = :status
            ORDER BY created_at DESC
        ");
        $stmt->execute(['status' => $status]);
        return $stmt->fetchAll();
    }

    /**
     * Одобрить запрос
     */
    public function approveRequest(int $requestId): bool
    {
        return $this->update($requestId, ['status' => 'approved']);
    }

    /**
     * Отклонить запрос
     */
    public function rejectRequest(int $requestId): bool
    {
        return $this->update($requestId, ['status' => 'rejected']);
    }
}