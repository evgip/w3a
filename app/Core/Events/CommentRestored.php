<?php

declare(strict_types=1);

namespace App\Core\Events;

/**
 * Событие восстановления ранее удалённого комментария.
 *
 * Генерируется после успешного восстановления комментария
 * (обнуления поля `deleted_at` в таблице `comments`).
 * Используется слушателями для:
 *  - Увеличения счётчика комментариев у истории (UpdateStoryCommentsCountListener)
 *  - Логирования действий пользователя (AuditListener)
 *
 * Важно: событие отправляется как при восстановлении автором, так и при восстановлении модератором.
 */
class CommentRestored extends Event
{
    /**
     * @param int  $commentId Уникальный идентификатор восстановленного комментария
     * @param int  $storyId   Идентификатор истории, к которой принадлежит комментарий
     * @param int  $userId    Идентификатор пользователя, выполнившего восстановление
     * @param bool $isAuthor  Признак того, что восстанавливающий является автором комментария
     */
    public function __construct(
        private int $commentId,
        private int $storyId,
        private int $userId,
        private bool $isAuthor = true
    ) {
    }

    /**
     * Получить идентификатор восстановленного комментария.
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
     * Получить идентификатор пользователя, выполнившего восстановление.
     *
     * @return int
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Проверить, является ли восстанавливающий автором комментария.
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
        return 'CommentRestored';
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