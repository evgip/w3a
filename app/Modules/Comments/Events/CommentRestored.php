<?php

declare(strict_types=1);

namespace App\Modules\Comments\Events;

use App\Core\Events\Event;

class CommentRestored extends Event
{
    public function __construct(
        private int $commentId,
        private int $storyId,
        private int $userId,
        private bool $isAuthor = false
    ) {}

    public function getName(): string
    {
        return 'moderation.comment_restored';
    }

    public function getCategory(): string
    {
        return 'moderation';
    }

    public function getData(): array
    {
        return [
            'comment_id' => $this->commentId,
            'story_id' => $this->storyId,
            'user_id' => $this->userId,
            'is_author' => $this->isAuthor,
            'description' => sprintf(
                'Пользователь (ID: %d) восстановил комментарий ID: %d в истории ID: %d%s',
                $this->userId,
                $this->commentId,
                $this->storyId,
                $this->isAuthor ? ' (автор комментария)' : ''
            ),
        ];
    }
}