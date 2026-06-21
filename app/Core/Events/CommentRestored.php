<?php

declare(strict_types=1);

namespace App\Core\Events;

/**
 * Событие восстановления удалённого комментария.
 */
class CommentRestored extends Event
{
    /**
     * @param int $commentId ID восстановленного комментария
     * @param int $storyId ID истории
     * @param int $userId ID пользователя, который восстановил
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
        return 'comment.restored';
    }

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
                'Пользователь (ID: %d) восстановил %s (комментарий ID: %d, история ID: %d)',
                $this->userId,
                $this->isAuthor ? 'свой комментарий' : 'чужой комментарий',
                $this->commentId,
                $this->storyId
            ),
        ];
    }
}