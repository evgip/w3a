<?php

declare(strict_types=1);

namespace App\Core\Events\Listeners;

use App\Core\Events\CommentCreated;
use App\Core\Events\CommentDeleted;
use App\Core\Events\CommentRestored;
use App\Modules\Stories\Models\Story;

/**
 * Слушатель для автоматического обновления счётчика комментариев в истории.
 * 
 * Реагирует на события:
 * - CommentCreated  → +1
 * - CommentDeleted  → -1 (soft delete)
 * - CommentRestored → +1
 */
class UpdateStoryCommentsCountListener
{
    private Story $storyModel;

    public function __construct()
    {
        $this->storyModel = new Story();
    }

    /**
     * Обработка создания комментария.
     */
    public function handleCreated(CommentCreated $event): void
    {
        $this->storyModel->incrementCommentsCount($event->storyId, 1);
    }

    /**
     * Обработка удаления комментария.
     */
    public function handleDeleted(CommentDeleted $event): void
    {
        $this->storyModel->incrementCommentsCount($event->storyId, -1);
    }

    /**
     * Обработка восстановления комментария.
     */
    public function handleRestored(CommentRestored $event): void
    {
        $this->storyModel->incrementCommentsCount($event->storyId, 1);
    }
}