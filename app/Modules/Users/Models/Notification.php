<?php

namespace App\Modules\Users\Models;

use App\Core\Model;

class Notification extends Model
{
    protected string $table = 'user_notifications';

	protected array $fillable = [
		'user_id',
		'is_read'
	];

    /**
     * Atomically flags all unread notification rows for a specific user as read
     */
    public function markAllAsRead(int $userId): void
    {
        $stmt = static::db()->prepare("UPDATE `user_notifications` SET `is_read` = 1 WHERE `user_id` = :uid AND `is_read` = 0");
        $stmt->execute(['uid' => $userId]);
    }
}
