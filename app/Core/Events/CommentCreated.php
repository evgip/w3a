<?php

declare(strict_types=1);

namespace App\Core\Events;

/**
 * Событие создания нового комментария.
 */
class CommentCreated extends Event
{
    /**
     * @param int $commentId ID созданного комментария
     * @param int $storyId ID истории
     * @param int $userId ID автора комментария
     * @param int|null $parentId ID родительского комментария (если это ответ)
     */
    public function __construct(
        private int $commentId,
        private int $storyId,
        private int $userId,
        private ?int $parentId = null
    ) {}

    public function getName(): string
    {
        return 'comment.created';
    }

    /**
     * Категория события.
     * Создание комментария — всегда обычное действие пользователя.
     */
    public function getCategory(): string
    {
        return 'general';
    }

    public function getData(): array
    {
        // Более информативное описание, если это ответ на другой комментарий
        if ($this->parentId !== null) {
            $description = sprintf(
                'Пользователь (ID: %d) ответил на комментарий ID: %d в истории ID: %d',
                $this->userId,
                $this->parentId,
                $this->storyId
            );
        } else {
            $description = sprintf(
                'Пользователь (ID: %d) оставил комментарий к истории ID: %d',
                $this->userId,
                $this->storyId
            );
        }

        return [
            'comment_id' => $this->commentId,
            'story_id' => $this->storyId,
            'user_id' => $this->userId,
            'parent_id' => $this->parentId,
            'description' => $description,
        ];
    }
}