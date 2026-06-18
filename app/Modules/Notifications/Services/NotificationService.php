<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Models\Notification;
use App\Modules\Stories\Models\Comment;
use App\Modules\Users\Models\User;

/**
 * Сервис для работы с уведомлениями.
 * Содержит только бизнес-логику, работа с БД делегирована моделям.
 */
class NotificationService
{
    private Notification $notificationModel;
    private Comment $commentModel;
    private User $userModel;

    public function __construct()
    {
        $this->notificationModel = new Notification();
        $this->commentModel = new Comment();
        $this->userModel = new User();
    }

    /**
     * Обработка создания нового комментария.
     * Уведомляет:
     * 1. Автора поста (если подписан)
     * 2. Автора родительского комментария (при ответе)
     * 3. Упомянутых пользователей (@username)
     */
    public function notifyCommentCreated(int $commentId): void
    {
        // Получаем полную информацию о комментарии через модель
        $comment = $this->commentModel->getWithStoryInfo($commentId);

        if (!$comment) {
            return;
        }

        $authorId = (int)$comment['author_id'];
        $storyAuthorId = (int)$comment['story_author_id'];
        $parentCommentId = $comment['parent_id'] ? (int)$comment['parent_id'] : null;

        // Исключаем автора из уведомлений
        $notifiedUserIds = [$authorId];

        // 1. Уведомление автору поста (если подписан)
        $this->notifyStoryAuthor(
            $storyAuthorId,
            $authorId,
            (int)$comment['id'],
            (bool)$comment['user_is_following'],
            $notifiedUserIds
        );

        // 2. Уведомление автору родительского комментария
        if ($parentCommentId) {
            $this->notifyParentCommentAuthor(
                $parentCommentId,
                $authorId,
                (int)$comment['id'],
                $notifiedUserIds
            );
        }

        // 3. Обработка упоминаний @username
        $this->processMentions(
            $comment['comment'],
            (int)$comment['id'],
            $authorId,
            $notifiedUserIds
        );
    }

    /**
     * Уведомляет автора поста о новом комментарии.
     */
    private function notifyStoryAuthor(
        int $storyAuthorId,
        int $commentAuthorId,
        int $commentId,
        bool $isFollowing,
        array &$notifiedUserIds
    ): void {
        if ($storyAuthorId === $commentAuthorId) {
            return; // Не уведомляем автора о его же комментарии
        }

        if (in_array($storyAuthorId, $notifiedUserIds)) {
            return; // Уже уведомлён
        }

        if (!$isFollowing) {
            return; // Автор не подписан на свой пост
        }

        $this->notificationModel->createReplyNotification(
            $storyAuthorId,
            $commentId,
            $commentAuthorId,
            'Прокомментировал вашу публикацию'
        );

        $notifiedUserIds[] = $storyAuthorId;
    }

    /**
     * Уведомляет автора родительского комментария об ответе.
     */
    private function notifyParentCommentAuthor(
        int $parentCommentId,
        int $commentAuthorId,
        int $commentId,
        array &$notifiedUserIds
    ): void {
        $parentAuthorId = $this->commentModel->getAuthorId($parentCommentId);

        if ($parentAuthorId === null) {
            return;
        }

        if ($parentAuthorId === $commentAuthorId) {
            return; // Не уведомляем автора о его же ответе
        }

        if (in_array($parentAuthorId, $notifiedUserIds)) {
            return; // Уже уведомлён
        }

        $this->notificationModel->createReplyNotification(
            $parentAuthorId,
            $commentId,
            $commentAuthorId,
            'Ответил на ваш комментарий'
        );

        $notifiedUserIds[] = $parentAuthorId;
    }

    /**
     * Обработка упоминаний @username в тексте.
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
            // Находим пользователя через модель
            $user = $this->userModel->findByName($username);

            if (!$user) {
                continue;
            }

            $userId = (int)$user['id'];

            // Не уведомляем автора и тех, кто уже получил уведомление
            if ($userId === $authorId || in_array($userId, $excludeUserIds)) {
                continue;
            }

            $this->notificationModel->createMentionNotification(
                $userId,
                $commentId,
                $authorId,
                'Упомянул вас в комментарии'
            );
        }
    }

    /**
     * Уведомление о новом личном сообщении.
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
     * Получить сводку уведомлений для пользователя.
     */
    public function getNotificationSummary(int $userId): array
    {
        return [
            'unread_count' => $this->notificationModel->getUnreadCount($userId),
            'recent' => $this->notificationModel->getUnreadNotifications($userId, 5)
        ];
    }
}
