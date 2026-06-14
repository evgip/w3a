<?php

namespace App\Modules\Messages\Models;

use App\Core\Model;
use App\Core\Database;

class Conversation extends Model
{
    protected string $table = 'conversations';

	protected array $fillable = [
		'user_one',
		'user_two',
		'updated_at'
	];

    /**
     * Получить ID получателя из беседы (тот участник, который не является отправителем)
     */
	public function getRecipientId(int $conversationId, int $senderId): int
	{
		$db = \App\Core\Database::getConnection();
		
		// Даем уникальные имена параметрам: sender_id_1, sender_id_2, sender_id_3
		$sql = "SELECT 
					CASE 
						WHEN user_one = :sender_id_1 THEN user_two 
						ELSE user_one 
					END as recipient_id
				FROM {$this->table} 
				WHERE id = :conversation_id 
				AND (user_one = :sender_id_2 OR user_two = :sender_id_3)
				LIMIT 1";
		
		$stmt = $db->prepare($sql);
		
		// Передаем значение $senderId для каждого уникального параметра
		$stmt->execute([
			'conversation_id' => $conversationId,
			'sender_id_1'     => $senderId,
			'sender_id_2'     => $senderId,
			'sender_id_3'     => $senderId,
		]);
		
		$result = $stmt->fetch(\PDO::FETCH_ASSOC);
		
		return $result ? (int)$result['recipient_id'] : 0;
	}

    /**
     * Получить или создать беседу между двумя пользователями
     */
    public function getOrCreateConversation(int $userOneId, int $userTwoId): int
    {
        // Проверяем, существует ли уже беседа
        $existing = $this->findFirst([
            'user_one' => $userOneId,
            'user_two' => $userTwoId
        ]);

        if ($existing) {
            return (int)$existing['id'];
        }

        // Проверяем обратный порядок
        $existing = $this->findFirst([
            'user_one' => $userTwoId,
            'user_two' => $userOneId
        ]);

        if ($existing) {
            return (int)$existing['id'];
        }

        // Создаём новую беседу
        $conversationId = $this->create([
            'user_one' => $userOneId,
            'user_two' => $userTwoId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return (int)$conversationId;
    }


   /**
     * Получить список бесед пользователя с последним сообщением
     */
	public function getUserConversations(int $userId): array
	{
		$db = \App\Core\Database::getConnection();
		
		// Даем уникальные имена параметрам: user_id_1, user_id_2, user_id_3
		$sql = "SELECT 
					c.*,
					u.username as participant_name,
					u.avatar as participant_avatar,
					m.message as last_message,
					m.created_at as last_message_time,
					m.sender_id as last_sender_id
				FROM {$this->table} c
				LEFT JOIN users u ON (
					CASE 
						WHEN c.user_one = :user_id_1 THEN c.user_two 
						ELSE c.user_one 
					END
				) = u.id
				LEFT JOIN messages m ON m.id = (
					SELECT MAX(id) FROM messages WHERE conversation_id = c.id
				)
				WHERE c.user_one = :user_id_2 OR c.user_two = :user_id_3
				ORDER BY c.updated_at DESC";
		
		$stmt = $db->prepare($sql);
		
		// Передаем значение $userId для каждого уникального параметра
		$stmt->execute([
			'user_id_1' => $userId,
			'user_id_2' => $userId,
			'user_id_3' => $userId,
		]);
		
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}
	
    /**
     * Пометить все сообщения в беседе как прочитанные
     */
    public function markAsRead(int $conversationId, int $userId): bool
    {
        $db = \App\Core\Database::getConnection();
        
        $sql = "UPDATE messages 
                SET is_read = 1 
                WHERE conversation_id = :conversation_id 
                AND sender_id != :user_id 
                AND is_read = 0";
        
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            'conversation_id' => $conversationId,
            'user_id' => $userId
        ]);
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
	
}
