<?php

declare(strict_types=1);

namespace App\Core\Events;

/**
 * Событие создания нового комментария.
 *
 * Генерируется после успешного сохранения комментария в базе данных.
 * Используется слушателями для:
 *  - Увеличения счётчика комментариев у истории (UpdateStoryCommentsCountListener)
 *  - Отправки уведомлений автору истории (NotificationListener)
 *  - Логирования действий пользователя (AuditListener)
 */
class CommentCreated extends Event
{
    /**
     * @param int      $commentId Уникальный идентификатор созданного комментария
     * @param int      $storyId   Идентификатор истории, к которой оставлен комментарий
     * @param int      $userId    Идентификатор автора комментария
     * @param int|null $parentId  Идентификатор родительского комментария (null, если это корневой комментарий)
     */
    public function __construct(
        private int $commentId,
        private int $storyId,
        private int $userId,
        private ?int $parentId = null
    ) {
    }

    /**
     * Получить идентификатор созданного комментария.
     *
     * @return int
     */
    public function getCommentId(): int
    {
        return $this->commentId;
    }

    /**
     * Получить идентификатор истории.
     *
     * @return int
     */
    public function getStoryId(): int
    {
        return $this->storyId;
    }

    /**
     * Получить идентификатор автора комментария.
     *
     * @return int
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Получить идентификатор родительского комментария.
     *
     * @return int|null
     */
    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    /**
     * Получить строковое имя события.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'CommentCreated';
    }

    /**
     * Получить массив данных события (для логирования и аудита).
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return [
            'comment_id' => $this->commentId,
            'story_id'   => $this->storyId,
            'user_id'    => $this->userId,
            'parent_id'  => $this->parentId,
        ];
    }
}