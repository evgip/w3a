<?php

namespace App\Modules\Notifications\Models;

use App\Core\Model;
use App\Core\Database;

class Notification extends Model
{
    protected string $table = 'user_notifications';

	protected array $fillable = [
		'notifiable_id',
		'user_id',
		'type',
		'notifiable_type',
		'actor_id',
		'message',
		'is_read'
	];

    // Типы уведомлений (как в Lobsters)
    public const TYPE_REPLY = 'reply';
    public const TYPE_MENTION = 'mention';
    public const TYPE_MESSAGE = 'message';

    // Типы сущностей для полиморфной связи
    public const ENTITY_COMMENT = 'Comment';
    public const ENTITY_MESSAGE = 'Message';
    public const ENTITY_STORY = 'Story';

    /**
     * Создать уведомление об ответе на комментарий
     */
    public function createReplyNotification(
        int $userId,
        int $commentId,
        int $actorId,
        string $message = null
    ): int {
        return $this->createNotification(
            $userId,
            self::TYPE_REPLY,
            self::ENTITY_COMMENT,
            $commentId,
            $actorId,
            $message ?? 'Ответил на ваш комментарий'
        );
    }

    /**
     * Создать уведомление об упоминании
     */
    public function createMentionNotification(
        int $userId,
        int $commentId,
        int $actorId,
        string $message = null
    ): int {
        return $this->createNotification(
            $userId,
            self::TYPE_MENTION,
            self::ENTITY_COMMENT,
            $commentId,
            $actorId,
            $message ?? 'Упомянул вас в комментарии'
        );
    }

    /**
     * Создать уведомление о личном сообщении
     */
    public function createMessageNotification(
        int $userId,
        int $messageId,
        int $actorId,
        string $message = null
    ): int {
        return $this->createNotification(
            $userId,
            self::TYPE_MESSAGE,
            self::ENTITY_MESSAGE,
            $messageId,
            $actorId,
            $message ?? 'Отправил вам сообщение'
        );
    }

    /**
     * Базовый метод создания уведомления
     */
    private function createNotification(
        int $userId,
        string $type,
        string $entityType,
        int $entityId,
        int $actorId,
        string $message
    ): int {
        // Проверяем, не существует ли уже такое уведомление (защита от дубликатов)
        $existing = $this->findFirst([
            'user_id' => $userId,
            'type' => $type,
            'notifiable_type' => $entityType,
            'notifiable_id' => $entityId
        ]);

        if ($existing) {
            return (int)$existing['id'];
        }

        return $this->create([
            'user_id' => $userId,
            'type' => $type,
            'notifiable_type' => $entityType,
            'notifiable_id' => $entityId,
            'actor_id' => $actorId,
            'message' => $message,
            'is_read' => 0
        ]);
    }

    /**
     * Найти первое уведомление по условиям
     */
    private function findFirst(array $conditions): ?array
    {
        $db = Database::getConnection();
        $where = [];
        $params = [];

        foreach ($conditions as $key => $value) {
            $where[] = "`{$key}` = :{$key}";
            $params[$key] = $value;
        }

        $sql = "SELECT * FROM `{$this->table}` WHERE " . implode(' AND ', $where) . " LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Получить все уведомления пользователя с пагинацией
     */
    public function getUserNotifications(int $userId, ?string $type = null, int $limit = 50): array
    {
        $db = Database::getConnection();
        $sql = "
            SELECT 
                n.*,
                u.username as actor_name,
                u.avatar as actor_avatar,
                c.comment as comment_text,
                c.story_id,
                s.title as story_title,
                m.message,
                m.conversation_id
            FROM `{$this->table}` n
            LEFT JOIN users u ON n.actor_id = u.id
            LEFT JOIN comments c ON n.notifiable_type = 'Comment' AND n.notifiable_id = c.id
            LEFT JOIN stories s ON c.story_id = s.id
            LEFT JOIN messages m ON n.notifiable_type = 'Message' AND n.notifiable_id = m.id
            WHERE n.user_id = :user_id
            ORDER BY n.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Получить только непрочитанные уведомления
     */
    public function getUnreadNotifications(int $userId, int $limit = 50): array
    {
        $db = Database::getConnection();
        $sql = "
            SELECT 
                n.*,
                u.username as actor_name,
                u.avatar as actor_avatar,
                c.comment as comment_text,
                c.story_id,
                s.title as story_title
            FROM `{$this->table}` n
            LEFT JOIN users u ON n.actor_id = u.id
            LEFT JOIN comments c ON n.notifiable_type = 'Comment' AND n.notifiable_id = c.id
            LEFT JOIN stories s ON c.story_id = s.id
            WHERE n.user_id = :user_id AND n.is_read = 0
            ORDER BY n.created_at DESC
            LIMIT :limit
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute(['user_id' => $userId, 'limit' => $limit]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Получить количество непрочитанных уведомлений
     */
    public function getUnreadCount(int $userId): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM `{$this->table}` 
            WHERE user_id = :user_id AND is_read = 0
        ");
        $stmt->execute(['user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    }

	/**
	 * Получить количество непрочитанных уведомлений по типам
	 * 
	 * @param int $userId ID пользователя
	 * @return array Массив вида [['type' => 'reply', 'count' => 5], ...]
	 */
	public function getUnreadCountByType(int $userId): array
	{
		$db = Database::getConnection();
		$stmt = $db->prepare("
			SELECT type, COUNT(*) as count 
			FROM `{$this->table}` 
			WHERE user_id = :user_id AND is_read = 0 
			GROUP BY type
		");
		$stmt->execute(['user_id' => $userId]);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

    /**
     * Пометить одно уведомление как прочитанное
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        return $this->update($notificationId, [
            'is_read' => 1,
            'read_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Пометить все уведомления пользователя как прочитанные
     */
    public function markAllAsRead(int $userId): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE `{$this->table}` 
            SET is_read = 1, read_at = NOW() 
            WHERE user_id = :user_id AND is_read = 0
        ");
        return $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Удалить старье уведомления (старше 30 дней)
     */
    public function cleanupOldNotifications(int $daysOld = 30): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            DELETE FROM `{$this->table}` 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
            AND is_read = 1
        ");
        $stmt->execute(['days' => $daysOld]);
        return $stmt->rowCount();
    }
}