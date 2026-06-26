<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Models\Notification;
use App\Modules\Stories\Models\Comment;
use App\Modules\Users\Models\User;

class NotificationService
{
    private Notification $notificationModel;
    private Comment $commentModel;
    private User $userModel;

    private const ALLOWED_TYPES = ['reply', 'mention', 'message'];
    private const DEFAULT_PER_PAGE = 25;

    /**
     * Конструктор с опциональными зависимостями.
     * Если зависимости не переданы — создаются автоматически.
     * Это упрощает использование сервиса в разных местах проекта.
     */
    public function __construct(
        ?Notification $notificationModel = null,
        ?Comment $commentModel = null,
        ?User $userModel = null
    ) {
        $this->notificationModel = $notificationModel ?? new Notification();
        $this->commentModel = $commentModel ?? new Comment();
        $this->userModel = $userModel ?? new User();
    }

    // =========================================================================
    // МЕТОДЫ ДЛЯ КОНТРОЛЛЕРА
    // =========================================================================

    public function getNotificationsForIndex(
        int $userId,
        string $type = 'all',
        int $page = 1,
        int $limit = self::DEFAULT_PER_PAGE
    ): array {
        $normalizedType = $this->normalizeType($type);

        $notifications = $this->getUserNotifications(
            $userId,
            $normalizedType,
            $limit,
            max(1, $page)
        );

        return [
            'notifications' => $notifications,
            'currentType' => $normalizedType ?? 'all',
            'counts' => $this->getUnreadCountsByType($userId),
            'totalUnread' => $this->getUnreadCount($userId),
            'allowedTypes' => self::ALLOWED_TYPES,
        ];
    }

    public function getUserNotifications(
        int $userId,
        ?string $type = null,
        int $limit = self::DEFAULT_PER_PAGE,
        int $page = 1
    ): array {
        if ($userId <= 0) {
            return [];
        }

        $normalizedType = $this->normalizeType($type);
        $limit = max(1, min($limit, 100));
        $page = max(1, $page);

        return $this->notificationModel->getUserNotifications(
            $userId,
            $normalizedType,
            $limit,
            $page
        );
    }

    public function getUnreadCountsByType(int $userId): array
    {
        if ($userId <= 0) {
            return ['reply' => 0, 'mention' => 0, 'message' => 0];
        }

        $unreadCounts = $this->notificationModel->getUnreadCountByType($userId);

        $counts = ['reply' => 0, 'mention' => 0, 'message' => 0];

        if (is_array($unreadCounts)) {
            foreach ($unreadCounts as $row) {
                $type = $row['type'] ?? null;
                if ($type && isset($counts[$type])) {
                    $counts[$type] = (int)$row['count'];
                }
            }
        }

        return $counts;
    }

    public function getUnreadCount(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        return (int)$this->notificationModel->getUnreadCount($userId);
    }

    public function markAsRead(int $notificationId, int $userId): bool
    {
        if ($notificationId <= 0 || $userId <= 0) {
            return false;
        }

        return $this->notificationModel->markAsRead($notificationId, $userId);
    }

    public function markAllAsRead(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return $this->notificationModel->markAllAsRead($userId);
    }

    public function markTypeAsRead(int $userId, string $type): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $normalizedType = $this->normalizeType($type);
        if ($normalizedType === null) {
            return false;
        }

        return $this->notificationModel->markTypeAsRead($userId, $normalizedType);
    }

    // =========================================================================
    // БИЗНЕС-ЛОГИКА СОЗДАНИЯ УВЕДОМЛЕНИЙ
    // =========================================================================

    public function notifyCommentCreated(int $commentId): void
    {
        if ($commentId <= 0) {
            return;
        }

        try {
            $comment = $this->commentModel->getWithStoryInfo($commentId);

            if (!$comment) {
                 return; // Тихий выход
            }

            $authorId = (int)($comment['author_id'] ?? 0);
            $storyAuthorId = (int)($comment['story_author_id'] ?? 0);
            $parentCommentId = !empty($comment['parent_id']) ? (int)$comment['parent_id'] : null;

            if ($authorId <= 0) {
                return;
            }

            $notifiedUserIds = [$authorId];

            if ($storyAuthorId > 0) {
                $this->notifyStoryAuthor(
                    $storyAuthorId,
                    $authorId,
                    (int)$comment['id'],
                    (bool)($comment['user_is_following'] ?? false),
                    $notifiedUserIds
                );
            }

            if ($parentCommentId) {
                $this->notifyParentCommentAuthor(
                    $parentCommentId,
                    $authorId,
                    (int)$comment['id'],
                    $notifiedUserIds
                );
            }

            $commentText = (string)($comment['comment'] ?? '');
            if (!empty($commentText)) {
                $this->processMentions(
                    $commentText,
                    (int)$comment['id'],
                    $authorId,
                    $notifiedUserIds
                );
            }

        } catch (\Throwable $e) {
            error_log("[NOTIFICATIONS] Error in notifyCommentCreated: " . $e->getMessage());
        }
    }

    public function notifyMessageSent(int $messageId, int $recipientId, int $senderId): void
    {
        if ($messageId <= 0 || $recipientId <= 0 || $senderId <= 0) {
            return;
        }

        if ($recipientId === $senderId) {
            return;
        }

		// ✅ Проверяем настройки пользователя
		if (!$this->userWantsNotification($recipientId, 'notify_on_message')) {
			return; // Пользователь отключил уведомления о личных сообщениях
		}

        try {
            $this->notificationModel->createMessageNotification(
                $recipientId,
                $messageId,
                $senderId,
                'Отправил вам личное сообщение'
            );
        } catch (\Throwable $e) {
            error_log("[NOTIFICATIONS] Error in notifyMessageSent: " . $e->getMessage());
        }
    }

    // =========================================================================
    // ПРИВАТНЫЕ ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    private function normalizeType(?string $type): ?string
    {
        if ($type === null || $type === '' || $type === 'all') {
            return null;
        }

        $type = strtolower(trim($type));

        if (in_array($type, self::ALLOWED_TYPES, true)) {
            return $type;
        }

        return null;
    }

	private function notifyStoryAuthor(
		int $storyAuthorId,
		int $commentAuthorId,
		int $commentId,
		bool $isFollowing,
		array &$notifiedUserIds
	): void {
		if ($storyAuthorId === $commentAuthorId) {
			return;
		}

		if (in_array($storyAuthorId, $notifiedUserIds, true)) {
			return;
		}

		if (!$isFollowing) {
			return;
		}

		// ✅ Проверяем настройки пользователя
		if (!$this->userWantsNotification($storyAuthorId, 'notify_on_story_comment')) {
			return; // Пользователь отключил уведомления о комментариях к историям
		}

		try {
			$this->notificationModel->createReplyNotification(
				$storyAuthorId,
				$commentId,
				$commentAuthorId,
				'Прокомментировал вашу публикацию'
			);
			$notifiedUserIds[] = $storyAuthorId;
		} catch (\Throwable $e) {
			error_log("[NOTIFICATIONS] Error notifying story author: " . $e->getMessage());
		}
	}

	private function notifyParentCommentAuthor(
		int $parentCommentId,
		int $commentAuthorId,
		int $commentId,
		array &$notifiedUserIds
	): void {
		try {
			$parentAuthorId = $this->commentModel->getAuthorId($parentCommentId);
		} catch (\Throwable $e) {
			error_log("[NOTIFICATIONS] Error getting parent author: " . $e->getMessage());
			return;
		}

		if ($parentAuthorId === null || $parentAuthorId <= 0) {
			return;
		}

		if ($parentAuthorId === $commentAuthorId) {
			return;
		}

		if (in_array($parentAuthorId, $notifiedUserIds, true)) {
			return;
		}

		// ✅ Проверяем настройки пользователя
		if (!$this->userWantsNotification($parentAuthorId, 'notify_on_reply')) {
			return; // Пользователь отключил уведомления об ответах
		}

		try {
			$this->notificationModel->createReplyNotification(
				$parentAuthorId,
				$commentId,
				$commentAuthorId,
				'Ответил на ваш комментарий'
			);
			$notifiedUserIds[] = $parentAuthorId;
		} catch (\Throwable $e) {
			error_log("[NOTIFICATIONS] Error notifying parent author: " . $e->getMessage());
		}
	}

    private function processMentions(
        string $text,
        int $commentId,
        int $authorId,
        array &$notifiedUserIds
    ): void {
        if (!preg_match_all('/@[a-zA-Z0-9_]+/', $text, $matches)) {
            return;
        }

        if (empty($matches[0])) {
            return;
        }

        $usernames = array_unique($matches[0]);

        foreach ($usernames as $usernameWithAt) {
            $username = ltrim($usernameWithAt, '@');

            if (empty($username)) {
                continue;
            }

            try {
                $user = $this->userModel->findByName($username);

                if (!$user) {
                    continue;
                }

                $userId = (int)($user['id'] ?? 0);

                if ($userId <= 0 || $userId === $authorId || in_array($userId, $notifiedUserIds, true)) {
                    continue;
                }
				
				// ✅ Проверяем настройки пользователя
				if (!$this->userWantsNotification($userId, 'notify_on_mention')) {
					continue;
				}

                $this->notificationModel->createMentionNotification(
                    $userId,
                    $commentId,
                    $authorId,
                    'Упомянул вас в комментарии'
                );

                $notifiedUserIds[] = $userId;

            } catch (\Throwable $e) {
                error_log("[NOTIFICATIONS] Error processing mention @{$username}: " . $e->getMessage());
            }
        }
    }
	
	/**
	 * Проверить, хочет ли пользователь получать уведомления данного типа.
	 *
	 * @param int    $userId  ID пользователя
	 * @param string $setting Название настройки (notify_on_reply, notify_on_story_comment)
	 * @return bool
	 */
	private function userWantsNotification(int $userId, string $setting): bool
	{
		$allowedSettings = [
			'notify_on_reply',
			'notify_on_story_comment',
			'notify_on_mention',
			'notify_on_message',
			'email_notifications',
		];

		if (!in_array($setting, $allowedSettings, true)) {
			return true;
		}

		try {
			$stmt = \App\Core\Model::db()->prepare("
				SELECT `{$setting}`
				FROM `user_settings`
				WHERE `user_id` = :user_id
				LIMIT 1
			");
			
			$stmt->execute(['user_id' => $userId]);
			$result = $stmt->fetch(\PDO::FETCH_ASSOC);

			// Если записи нет — считаем, что пользователь хочет уведомления (default = 1)
			if (!$result) {
				return true;
			}

			return (bool) $result[$setting];
			
		} catch (\Throwable $e) {
			error_log("[NOTIFICATIONS] Error checking user settings: " . $e->getMessage());
			return true; // При ошибке — уведомляем (безопасное поведение по умолчанию)
		}
	}
}