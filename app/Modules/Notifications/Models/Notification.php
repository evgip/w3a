<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Core\Model;

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
        'is_read',
        'read_at'
    ];
 

    // Типы уведомлений
    public const TYPE_REPLY = 'reply';  // Ответ на ваш комментарий
    public const TYPE_MENTION = 'mention';  // Упоминание @username
    public const TYPE_MESSAGE = 'message';

    // Типы сущностей для полиморфной связи
    public const ENTITY_COMMENT = 'Comment';
    public const ENTITY_MESSAGE = 'Message';
    public const ENTITY_STORY = 'Story';

	public const TYPE_BAN = 'ban';
	public const TYPE_UNBAN = 'unban';
	
	public const TYPE_DEACTIVATED = 'deactivated';  // ← ДОБАВЬТЕ
	public const TYPE_ACTIVATED = 'activated';      // ← ДОБАВЬТЕ

    const TYPE_STORY_COMMENT = 'story_comment'; // Новый комментарий в истории, на которую подписаны
    const TYPE_STORY_REPLY = 'story_reply';  // Ответ в ветке вашей истории

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
	 * Создать уведомление о деактивации аккаунта
	 */
	public function createDeactivatedNotification(
		int $userId,
		int $adminId
	): int {
		return $this->createNotification(
			$userId,
			self::TYPE_DEACTIVATED,
			'User',
			$userId,
			$adminId,
			'Ваша учетная запись была деактивирована администратором. Обратитесь в поддержку для восстановления доступа.'
		);
	}

	/**
	 * Создать уведомление об активации аккаунта
	 */
	public function createActivatedNotification(
		int $userId,
		int $adminId
	): int {
		return $this->createNotification(
			$userId,
			self::TYPE_ACTIVATED,
			'User',
			$userId,
			$adminId,
			'Ваша учетная запись была активирована администратором. Добро пожаловать!'
		);
	}

    /**
     * Найти первое уведомление по условиям
     */
    private function findFirst(array $conditions): ?array
    {
        $where = [];
        $params = [];

        foreach ($conditions as $key => $value) {
            $where[] = "`{$key}` = :{$key}";
            $params[$key] = $value;
        }

        $sql = "SELECT * FROM `{$this->table}` WHERE " . implode(' AND ', $where) . " LIMIT 1";
        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Получить уведомления пользователя с пагинацией и фильтрацией по типу
     */
    public function getUserNotifications(int $userId, ?string $type = null, int $limit = 25, int $page = 1): array
    {
        $where = "n.user_id = :user_id";
        $params = [
            'user_id' => $userId,
            'limit'   => $limit,
            'offset'  => ($page - 1) * $limit // Автоматически считаем OFFSET от номера страницы
        ];

        // Если передан конкретный тип (и это не 'all'), добавляем фильтр по полю type
        if ($type && $type !== 'all') {
            $where .= " AND n.type = :type";
            $params['type'] = $type;
        }

        $sql = "
		SELECT
			n.*,
			u.username as actor_name,
			up.avatar as actor_avatar,
			c.comment as comment_text,
			c.story_id,
			s.title as story_title,
			m.message,
			m.conversation_id
		FROM `{$this->table}` n
		LEFT JOIN users u ON n.actor_id = u.id
		LEFT JOIN `user_profiles` up ON u.id = up.user_id
		LEFT JOIN comments c ON n.notifiable_type = 'Comment' AND n.notifiable_id = c.id
		LEFT JOIN stories s ON c.story_id = s.id
		LEFT JOIN messages m ON n.notifiable_type = 'Message' AND n.notifiable_id = m.id
		WHERE {$where}
		ORDER BY n.created_at DESC
		LIMIT :limit OFFSET :offset
		";

        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }



    /**
     * Получить только непрочитанные уведомления
     */
    public function getUnreadNotifications(int $userId, int $limit = 50): array
    {
        $sql = "
            SELECT 
                n.*,
                u.username as actor_name,
                up.avatar as actor_avatar,
                c.comment as comment_text,
                c.story_id,
                s.title as story_title
            FROM `{$this->table}` n
            LEFT JOIN users u ON n.actor_id = u.id
			LEFT JOIN `user_profiles` up ON u.id = up.user_id
            LEFT JOIN comments c ON n.notifiable_type = 'Comment' AND n.notifiable_id = c.id
            LEFT JOIN stories s ON c.story_id = s.id
            WHERE n.user_id = :user_id AND n.is_read = 0
            ORDER BY n.created_at DESC
            LIMIT :limit
        ";

        $stmt = static::db()->prepare($sql);
        $stmt->execute(['user_id' => $userId, 'limit' => $limit]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Получить количество непрочитанных уведомлений
     */
    public function getUnreadCount(int $userId): int
    {
        $stmt = static::db()->prepare("
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
        $stmt = static::db()->prepare("
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
        $stmt = static::db()->prepare("
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
        $stmt = static::db()->prepare("
            DELETE FROM `{$this->table}` 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
            AND is_read = 1
        ");
        $stmt->execute(['days' => $daysOld]);
        return $stmt->rowCount();
    }

	/**
	 * Получить список пользователей, которых нужно уведомить о новом комментарии.
	 *
	 * @param int $storyId ID истории
	 * @param int $commentId ID комментария
	 * @param int $commentAuthorId ID автора комментария (исключается из уведомлений)
	 * @return array Массив пользователей для уведомления
	 */
	public function getUsersToNotify(int $storyId, int $commentId, int $commentAuthorId): array
	{
		$usersToNotify = [];
		$db = static::db();
		
		// Получаем данные комментария (включая parent_id)
		$stmt = $db->prepare("
			SELECT user_id, parent_id, comment 
			FROM comments 
			WHERE id = :comment_id
		");
		$stmt->execute(['comment_id' => $commentId]);
		$comment = $stmt->fetch(\PDO::FETCH_ASSOC);
		
		if (!$comment) {
			return [];
		}
		
		// 1. Уведомить автора родительского комментария (если это ответ)
		if (!empty($comment['parent_id'])) {
			$stmt = $db->prepare("
				SELECT user_id FROM comments WHERE id = :parent_id
			");
			$stmt->execute(['parent_id' => (int)$comment['parent_id']]);
			$parentAuthorId = $stmt->fetchColumn();
			
			if ($parentAuthorId && (int)$parentAuthorId !== $commentAuthorId) {
				// Проверяем настройки пользователя
				if ($this->userWantsNotification((int)$parentAuthorId, 'notify_on_reply')) {
					$usersToNotify[] = [
						'user_id' => (int)$parentAuthorId,
						'type' => self::TYPE_REPLY,
					];
				}
			}
		}
		
		// 2. Уведомить автора истории, если он подписан и не является автором комментария
		$stmt = $db->prepare("
			SELECT user_id, user_is_following FROM stories WHERE id = :story_id
		");
		$stmt->execute(['story_id' => $storyId]);
		$story = $stmt->fetch(\PDO::FETCH_ASSOC);
		
		if ($story && $story['user_is_following'] && (int)$story['user_id'] !== $commentAuthorId) {
			// Не дублируем, если автор истории уже получил уведомление как автор родительского коммента
			$alreadyNotified = array_column($usersToNotify, 'user_id');
			
			if (!in_array((int)$story['user_id'], $alreadyNotified)) {
				if ($this->userWantsNotification((int)$story['user_id'], 'notify_on_story_comment')) {
					$usersToNotify[] = [
						'user_id' => (int)$story['user_id'],
						'type' => self::TYPE_STORY_COMMENT,
					];
				}
			}
		}
		
		// 3. Обработка @упоминаний в тексте комментария
		$mentionedUsers = $this->extractMentions($comment['comment'] ?? '');
		foreach ($mentionedUsers as $mentionedUserId) {
			if ($mentionedUserId === $commentAuthorId) {
				continue; // Не уведомляем автора о самом себе
			}
			
			$alreadyNotified = array_column($usersToNotify, 'user_id');
			if (!in_array($mentionedUserId, $alreadyNotified)) {
				$usersToNotify[] = [
					'user_id' => $mentionedUserId,
					'type' => self::TYPE_MENTION,
				];
			}
		}
		
		return $usersToNotify;
	}

	/**
	 * Создать уведомления для всех заинтересованных пользователей.
	 *
	 * @param int $storyId ID истории
	 * @param int $commentId ID комментария
	 * @param int $commentAuthorId ID автора комментария
	 */
	public function createForComment(int $storyId, int $commentId, int $commentAuthorId): void
	{
		$usersToNotify = $this->getUsersToNotify($storyId, $commentId, $commentAuthorId);
		
		foreach ($usersToNotify as $notify) {
			// Используем правильный метод createNotification (приватный) через публичные методы
			switch ($notify['type']) {
				case self::TYPE_REPLY:
					$this->createReplyNotification(
						$notify['user_id'],
						$commentId,
						$commentAuthorId
					);
					break;
					
				case self::TYPE_STORY_COMMENT:
					$this->createReplyNotification(
						$notify['user_id'],
						$commentId,
						$commentAuthorId,
						'Прокомментировал вашу публикацию'
					);
					break;
					
				case self::TYPE_MENTION:
					$this->createMentionNotification(
						$notify['user_id'],
						$commentId,
						$commentAuthorId
					);
					break;
			}
		}
	}
}
