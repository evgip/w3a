<?php

namespace App\Modules\Messages\Models;

use App\Core\Model;
use App\Core\Database;

class Conversation extends Model
{
    protected string $table = 'conversations';

    /**
     * Locate all direct message channels active for the currently authenticated account user
     */
    public function getUserConversations(int $userId): array
    {
        $db = Database::getConnection();
        
        // Complex query pulling current conversation rows, matching secondary names, and grabbing the last message
        $sql = "SELECT c.id as conversation_id, c.updated_at,
                       u.id as participant_id, u.name as participant_name, u.avatar as participant_avatar,
                       m.message as last_message, m.sender_id as last_sender_id, m.is_read
                FROM `conversations` c
                JOIN `users` u ON (c.user_one = u.id AND c.user_two = :uid1) OR (c.user_two = u.id AND c.user_one = :uid2)
                LEFT JOIN `messages` m ON m.id = (
                    SELECT id FROM `messages` WHERE conversation_id = c.id ORDER BY id DESC LIMIT 1
                )
                WHERE c.user_one = :uid3 OR c.user_two = :uid4
                ORDER BY c.updated_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute(['uid1' => $userId, 'uid2' => $userId, 'uid3' => $userId, 'uid4' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Find or create a distinct private dialogue room channel key between two unique users
     */
    public function firstOrCreate(int $userOne, int $userTwo): int
    {
        $db = Database::getConnection();

        // Enforce chronological sorting properties to avoid structural duplication bugs (Low ID always fits user_one slot)
        $first  = min($userOne, $userTwo);
        $second = max($userOne, $userTwo);

        $stmt = $db->prepare("SELECT `id` FROM `conversations` WHERE `user_one` = :u1 AND `user_two` = :u2 LIMIT 1");
        $stmt->execute(['u1' => $first, 'u2' => $second]);
        $id = $stmt->fetchColumn();

        if ($id !== false) {
            return (int)$id;
        }

        // Create fresh conversation channel if none exists
        return $this->create([
            'user_one' => $first,
            'user_two' => $second
        ]);
    }
	
    /**
     * Count the total number of unread conversations for the current user
     */
    public function getUnreadCount(int $userId): int
    {
        $db = Database::getConnection();
        
        // Counts messages where the user is a participant but NOT the sender, and is_read is 0
        $sql = "SELECT COUNT(DISTINCT conversation_id) 
                FROM `messages` m
                JOIN `conversations` c ON m.conversation_id = c.id
                WHERE (c.user_one = :uid1 OR c.user_two = :uid2)
                  AND m.sender_id != :uid3 
                  AND m.is_read = 0 
                  AND m.deleted_at IS NULL";

        $stmt = $db->prepare($sql);
        $stmt->execute(['uid1' => $userId, 'uid2' => $userId, 'uid3' => $userId]);
        return (int)$stmt->fetchColumn();
    }
}
