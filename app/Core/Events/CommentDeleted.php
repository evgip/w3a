<?php

declare(strict_types=1);

namespace App\Core\Events;

/**
 * Событие удаления комментария (мягкое удаление).
 *
 * Генерируется после успешного мягкого удаления комментария
 * (установки поля `deleted_at` в таблице `comments`).
 * Используется слушателями для:
 *  - Уменьшения счётчика комментариев у истории (UpdateStoryCommentsCountListener)
 *  - Логирования действий пользователя (AuditListener)
 *
 * Важно: событие отправляется как при удалении автором, так и при удалении модератором.
 */
class CommentDeleted extends Event
{
    /**
     * @param int  $commentId Уникальный идентификатор удалённого комментария
     * @param int  $storyId   Идентификатор истории, к которой принадлежит комментарий
     * @param int  $userId    Идентификатор пользователя, выполнившего удаление
     * @param bool $isAuthor  Признак того, что удаляющий является автором комментария
     */
    public function __construct(
        private int $commentId,
        private int $storyId,
        private int $userId,
        private bool $isAuthor = true
    ) {
    }

    /**
     * Получить идентификатор удалённого комментария.
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
     * Получить идентификатор пользователя, выполнившего удаление.
     *
     * @return int
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Проверить, является ли удаляющий автором комментария.
     *
     * @return bool
     */
    public function isAuthor(): bool
    {
        return $this->isAuthor;
    }

    /**
     * Получить строковое имя события.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'CommentDeleted';
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
            'is_author'  => $this->isAuthor,
        ];
    }
}