<?php

declare(strict_types=1);

namespace App\Core\Events;

/**
 * Событие удаления комментария.
 */
class CommentDeleted extends Event
{
    /**
     * @param int $commentId ID удалённого комментария
     * @param int $storyId ID истории (для редиректа и контекста)
     * @param int $userId ID пользователя, который удалил
     * @param bool $isAuthor Является ли пользователь автором комментария
     */
    public function __construct(
        private int $commentId,
        private int $storyId,
        private int $userId,
        private bool $isAuthor = true
    ) {}

    public function getName(): string
    {
        return 'comment.deleted';
    }

    /**
     * Категория события.
     * Если удалил автор — обычное действие (general).
     * Если удалил модератор — модерация.
     */
    public function getCategory(): string
    {
        return $this->isAuthor ? 'general' : 'moderation';
    }

    public function getData(): array
    {
        return [
            'comment_id' => $this->commentId,
            'story_id' => $this->storyId,
            'user_id' => $this->userId,
            'is_author' => $this->isAuthor,
            'description' => sprintf(
                'Пользователь (ID: %d) удалил %s (комментарий ID: %d, история ID: %d)',
                $this->userId,
                $this->isAuthor ? 'свой комментарий' : 'чужой комментарий',
                $this->commentId,
                $this->storyId
            ),
        ];
    }
}