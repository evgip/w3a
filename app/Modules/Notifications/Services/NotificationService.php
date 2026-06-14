<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Models\Notification;
use App\Core\Database;

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
        $db = Database::getConnection();

        // Получаем полную информацию о комментарии
        $stmt = $db->prepare("
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

        $authorId = (int)$comment['author_id'];
        $notifiedUserIds = [];

        // 1. Уведомление автору родительского комментария (ОТВЕТ)
        if (!empty($comment['parent_id'])) {
            $parentStmt = $db->prepare("
                SELECT user_id FROM comments WHERE id = :parent_id
            ");
            $parentStmt->execute(['parent_id' => $comment['parent_id']]);
            $parentComment = $parentStmt->fetch(\PDO::FETCH_ASSOC);

            if ($parentComment && (int)$parentComment['user_id'] !== $authorId) {
                $parentAuthorId = (int)$parentComment['user_id'];
                
                $this->notificationModel->createReplyNotification(
                    $parentAuthorId,
                    $commentId,
                    $authorId,
                    'Ответил на ваш комментарий'
                );
                
                $notifiedUserIds[] = $parentAuthorId;
            }
        }

        // 2. Уведомление автору истории (если это не тот же, кто ответил)
        $storyAuthorId = (int)$comment['story_author_id'];
        if ($storyAuthorId !== $authorId && !in_array($storyAuthorId, $notifiedUserIds)) {
            $this->notificationModel->createReplyNotification(
                $storyAuthorId,
                $commentId,
                $authorId,
                'Прокомментировал вашу публикацию'
            );
            $notifiedUserIds[] = $storyAuthorId;
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
        $db = Database::getConnection();

        foreach ($usernames as $username) {
            // Находим пользователя по имени
            $stmt = $db->prepare("
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