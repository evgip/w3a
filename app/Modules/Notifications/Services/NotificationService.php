<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Models\Notification;
use App\Modules\Stories\Models\Comment;
use App\Modules\Users\Models\User;

/**
 * Сервис для управления уведомлениями пользователей.
 *
 * Позволяет получать, помечать как прочитанные и создавать уведомления различных типов
 * (ответы, упоминания, личные сообщения), учитывая настройки приватности пользователя.
 */
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
     *
     * @param Notification|null $notificationModel Модель уведомлений.
     * @param Comment|null $commentModel Модель комментариев.
     * @param User|null $userModel Модель пользователя.
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

    /**
     * Подготавливает данные для отображения списка уведомлений (для контроллера).
     *
     * @param int $userId ID пользователя.
     * @param string $type Тип уведомлений (`'all'`, `'reply'`, `'mention'`, `'message'`).
     * @param int $page Номер страницы пагинации.
     * @param int $limit Количество уведомлений на страницу (по умолчанию 25).
     * @return array Данные для представления: массив уведомлений, текущий фильтр, счетчики по типам и т.д.
     */
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

    /**
     * Получает список уведомлений пользователя.
     *
     * @param int $userId ID пользователя.
     * @param string|null $type Тип уведомлений (`'reply'`, `'mention'`, `'message'` или `null` для всех).
     * @param int $limit Количество уведомлений.
     * @param int $page Номер страницы.
     * @return array Массив уведомлений (каждое — ассоциативный массив).
     */
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

    /**
     * Получает количество непрочитанных уведомлений по типам для указанного пользователя.
     *
     * @param int $userId ID пользователя.
     * @return array Ассоциативный массив: ['reply' => int, 'mention' => int, 'message' => int].
     */
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

    /**
     * Получает общее количество непрочитанных уведомлений для пользователя.
     *
     * @param int $userId ID пользователя.
     * @return int Количество непрочитанных уведомлений.
     */
    public function getUnreadCount(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        return (int)$this->notificationModel->getUnreadCount($userId);
    }

    /**
     * Помечает одно уведомление как прочитанное.
     *
     * @param int $notificationId ID уведомления.
     * @param int $userId ID пользователя.
     * @return bool true, если пометка успешна; false — при ошибке.
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        if ($notificationId <= 0 || $userId <= 0) {
            return false;
        }

        return $this->notificationModel->markAsRead($notificationId, $userId);
    }

    /**
     * Помечает все уведомления пользователя как прочитанные.
     *
     * @param int $userId ID пользователя.
     * @return bool true, если операция успешна; false — при ошибке.
     */
    public function markAllAsRead(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return $this->notificationModel->markAllAsRead($userId);
    }

    // =========================================================================
    // БИЗНЕС-ЛОГИКА СОЗДАНИЯ УВЕДОМЛЕНИЙ
    // =========================================================================

    /**
     * Обрабатывает создание нового комментария и инициирует отправку уведомлений.
     *
     * Уведомления отправляются:
     * - автору истории (если он не автор комментария и не отключил уведомления),
     * - автору родительского комментария (если есть),
     * - пользователям, упомянутым в комментарии.
     *
     * @param int $commentId ID созданного комментария.
     * @return void
     */
    public function notifyCommentCreated(int $commentId): void
    {
        if ($commentId <= 0) {
            return;
        }

        try {
            $comment = $this->commentModel->getWithStoryInfo($commentId);

            if (!$comment) {
                return;
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

    /**
     * Создаёт уведомление о новом личном сообщении.
     *
     * Проверяет, включены ли у получателя уведомления о сообщениях.
     *
     * @param int $messageId ID сообщения.
     * @param int $recipientId ID получателя.
     * @param int $senderId ID отправителя.
     * @return void
     */
    public function notifyMessageSent(int $messageId, int $recipientId, int $senderId): void
    {
        if ($messageId <= 0 || $recipientId <= 0 || $senderId <= 0) {
            return;
        }

        if ($recipientId === $senderId) {
            return;
        }

        if (!$this->notificationModel->userWantsNotification($recipientId, 'notify_on_message')) {
            return;
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

    /**
     * Нормализует строку с типом уведомления.
     *
     * Приводит к нижнему регистру, обрезает пробелы и проверяет допустимость.
     * Если тип пустой или `'all'`, возвращает `null` (означает «все типы»).
     *
     * @param string|null $type Исходный тип.
     * @return string|null Нормализованный тип или null.
     */
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

    /**
     * Отправляет уведомление автору истории о комментарии к ней.
     *
     * Проверяет:
     * - совпадение авторов (нет уведомления самому себе),
     * - наличие в списке уже уведомлённых,
     * - состояние подписки (`user_is_following`),
     * - настройки приватности (`notify_on_story_comment`).
     *
     * @param int $storyAuthorId ID автора истории.
     * @param int $commentAuthorId ID автора комментария.
     * @param int $commentId ID комментария.
     * @param bool $isFollowing Флаг: подписан ли автор истории на комментарии.
     * @param array &$notifiedUserIds Список ID уже уведомлённых пользователей (по ссылке).
     * @return void
     */
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

        if (!$this->notificationModel->userWantsNotification($storyAuthorId, 'notify_on_story_comment')) {
            return;
        }

        try {
            $this->notificationModel->createStoryCommentNotification(
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

    /**
     * Отправляет уведомление автору родительского комментария об ответе.
     *
     * Проверяет:
     * - наличие родительского комментария,
     * - совпадение авторов,
     * - уже уведомлённых,
     * - настройки приватности (`notify_on_reply`).
     *
     * @param int $parentCommentId ID родительского комментария.
     * @param int $commentAuthorId ID автора нового комментария.
     * @param int $commentId ID нового комментария.
     * @param array &$notifiedUserIds Список ID уже уведомлённых пользователей.
     * @return void
     */
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

        if (!$this->notificationModel->userWantsNotification($parentAuthorId, 'notify_on_reply')) {
            return;
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

    /**
     * Ищет упоминания (`@username`) в тексте комментария и отправляет уведомления.
     *
     * Проверяет:
     * - существование упомянутого пользователя,
     * - совпадение с автором комментария,
     * - уже уведомлённых,
     * - настройки приватности (`notify_on_mention`).
     *
     * @param string $text Текст комментария.
     * @param int $commentId ID комментария.
     * @param int $authorId ID автора комментария.
     * @param array &$notifiedUserIds Список ID уже уведомлённых пользователей.
     * @return void
     */
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

                if (!$this->notificationModel->userWantsNotification($userId, 'notify_on_mention')) {
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
}
