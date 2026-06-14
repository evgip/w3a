<?php

namespace App\Modules\Messages\Models;

use App\Core\Model;
use App\Core\Database;

class Message extends Model
{
    protected string $table = 'messages';

	protected array $fillable = [
		'sender_id',
		'conversation_id',
		'message',
		'is_read'
	];

    /**
     * Fetch a paginated chunk of messages for a chat room, sorted chronologically
     */
    public function getPaginatedChatHistory(int $conversationId, int $limit = 15, int $offset = 0): array
    {
        $db = Database::getConnection();
        
        // Strategy: Select the most recent chunk using a subquery, then re-sort them chronologically for the view
        $sql = "SELECT * FROM (
                    SELECT m.*, u.username as sender_name, u.avatar as sender_avatar 
                    FROM `messages` m
                    JOIN `users` u ON m.sender_id = u.id
                    WHERE m.conversation_id = :cid AND m.deleted_at IS NULL
                    ORDER BY m.id DESC 
                    LIMIT :limit OFFSET :offset
                ) sub 
                ORDER BY id ASC";
                
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':cid', $conversationId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Calculate the absolute total message volume inside a specific chat room
     */
    public function getTotalMessageCount(int $conversationId): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM `messages` WHERE `conversation_id` = :cid AND `deleted_at` IS NULL");
        $stmt->execute(['cid' => $conversationId]);
        return (int)$stmt->fetchColumn();
    }


    /**
     * Fetch standard linear message payload stacks bound to an conversation room channel key
     */
    public function getChatHistory(int $conversationId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT m.*, u.username as sender_name, u.avatar as sender_avatar 
            FROM `messages` m
            JOIN `users` u ON m.sender_id = u.id
            WHERE m.conversation_id = :cid AND m.deleted_at IS NULL
            ORDER BY m.id ASC LIMIT 100
        ");
        $stmt->execute(['cid' => $conversationId]);
        return $stmt->fetchAll();
    }

    /**
     * Instantly mark an conversation message thread bundle as read internally
     */
    public function markAsRead(int $conversationId, int $readerId): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE `messages` SET `is_read` = 1 WHERE `conversation_id` = :cid AND `sender_id` != :rid AND `is_read` = 0");
        $stmt->execute(['cid' => $conversationId, 'rid' => $readerId]);
    }
	
    /**
     * Atomically counts the total number of unread incoming messages for a specific user
     */
    public function getUnreadCount(int $userId): int
    {
        $db = Database::getConnection();
        
        // Count any unread messages from conversations where the user is a participant but not the sender
        $sql = "SELECT COUNT(*) 
                FROM `messages` m
                JOIN `conversations` c ON m.conversation_id = c.id
                WHERE m.is_read = 0 
                  AND m.sender_id != :uid1
                  AND (c.user_one = :uid2 OR c.user_two = :uid3)";

        $stmt = $db->prepare($sql);
        $stmt->execute(['uid1' => $userId, 'uid2' => $userId, 'uid3' => $userId]);
        
        return (int)$stmt->fetchColumn();
    }
}
