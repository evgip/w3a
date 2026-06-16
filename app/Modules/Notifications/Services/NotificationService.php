<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Models\Notification;

class NotificationService
{
    private Notification $notificationModel;

    public function __construct()
    {
        $this->notificationModel = new Notification();
    }

    /**
     * Обработка создания нового комментария (аналог NotifyCommentJob из Lobsters)
     */
	public function notifyCommentCreated(int $commentId): void
	{
        // Получаем полную информацию о комментарии
        $stmt = static::db()->prepare("
            SELECT 
                c.id,
                c.user_id as author_id,
                c.parent_id,
                c.story_id,
                c.comment,
                s.user_id as story_author_id,
                s.title as story_title
            FROM comments c
            JOIN stories s ON c.story_id = s.id
            WHERE c.id = :comment_id
        ");
        $stmt->execute(['comment_id' => $commentId]);
        $comment = $stmt->fetch(\PDO::FETCH_ASSOC);

		if (!$comment) {
			return;
		}

		$authorId        = (int)$comment['author_id'];
		$storyAuthorId   = (int)$comment['story_author_id'];
		$parentCommentId = $comment['parent_id'] ? (int)$comment['parent_id'] : null;
		
		// Инициализируем список уже уведомлённых, чтобы исключить дубли
		$notifiedUserIds = [$authorId];

		// 1. Уведомление автору поста ТОЛЬКО если он подписан на свой пост
		if ($storyAuthorId !== $authorId && !in_array($storyAuthorId, $notifiedUserIds)) {
			// Проверяем флаг подписки из результата запроса
			if ((bool)$comment['user_is_following']) {
				$this->notificationModel->createReplyNotification(
					$storyAuthorId,
					$comment['comment_id'],
					$authorId,
					'Прокомментировал вашу публикацию'
				);
				$notifiedUserIds[] = $storyAuthorId;
			}
		}

		// 2. Уведомление автору родительского комментария (ответ на комментарий)
		if ($parentCommentId) {
			$stmt = $this->db->prepare("SELECT author_id FROM comments WHERE id = ?");
			$stmt->execute([$parentCommentId]);
			$parentComment = $stmt->fetch();

			if ($parentComment) {
				$parentAuthorId = (int)$parentComment['author_id'];
				
				if ($parentAuthorId !== $authorId && !in_array($parentAuthorId, $notifiedUserIds)) {
					$this->notificationModel->createReplyNotification(
						$parentAuthorId,
						$comment['comment_id'],
						$authorId,
						'Ответил на ваш комментарий'
					);
					$notifiedUserIds[] = $parentAuthorId;
				}
			}
		}

			// 3. Обработка упоминаний @username
			$this->processMentions(
				$comment['comment'],
				$commentId,
				$authorId,
				$notifiedUserIds
			);
	}
		

    /**
     * Обработка упоминаний @username в тексте (как в Lobsters)
     */
    private function processMentions(
        string $text,
        int $commentId,
        int $authorId,
        array $excludeUserIds
    ): void {
        // Ищем все @username в тексте
        preg_match_all('/@([a-zA-Z0-9_]+)/', $text, $matches);

        if (empty($matches[1])) {
            return;
        }

        $usernames = array_unique($matches[1]);

        foreach ($usernames as $username) {
            // Находим пользователя по имени
            $stmt = static::db()->prepare("
                SELECT id FROM users 
                WHERE name = :username AND deleted_at IS NULL
            ");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($user) {
                $userId = (int)$user['id'];
                
                // Не уведомляем автора и тех, кто уже получил уведомление
                if ($userId !== $authorId && !in_array($userId, $excludeUserIds)) {
                    $this->notificationModel->createMentionNotification(
                        $userId,
                        $commentId,
                        $authorId,
                        'Упомянул вас в комментарии'
                    );
                }
            }
        }
    }

    /**
     * Уведомление о новом личном сообщении
     */
    public function notifyMessageSent(int $messageId, int $recipientId, int $senderId): void
    {
        $this->notificationModel->createMessageNotification(
            $recipientId,
            $messageId,
            $senderId,
            'Отправил вам личное сообщение'
        );
    }

    /**
     * Получить сводку уведомлений для пользователя
     */
    public function getNotificationSummary(int $userId): array
    {
        return [
            'unread_count' => $this->notificationModel->getUnreadCount($userId),
            'recent' => $this->notificationModel->getUnreadNotifications($userId, 5)
        ];
    }
}