<?php

namespace App\Modules\Users\Models;

use App\Core\Model;
use App\Core\Database;

class Notification extends Model
{
    protected string $table = 'user_notifications';

    /**
     * Fetch unarchived notifications bound to a specific account user
     */
    public function getActiveNotifications(int $userId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM `user_notifications` WHERE `user_id` = :uid ORDER BY `id` DESC LIMIT 50");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Atomically flags all unread notification rows for a specific user as read
     */
    public function markAllAsRead(int $userId): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE `user_notifications` SET `is_read` = 1 WHERE `user_id` = :uid AND `is_read` = 0");
        $stmt->execute(['uid' => $userId]);
    }
}
