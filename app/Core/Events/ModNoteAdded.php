<?php

declare(strict_types=1);

namespace App\Core\Events;

class ModNoteAdded extends Event
{
    public function __construct(
        private int $moderatorId,
        private int $targetUserId,
        private string $notePreview
    ) {}

    public function getName(): string
    {
        return 'moderation.note_added';
    }

    public function getCategory(): string
    {
        return 'moderation';
    }

    public function getData(): array
    {
        return [
            'moderator_id'   => $this->moderatorId,
            'target_user_id' => $this->targetUserId,
            'note_preview'   => $this->notePreview,
            'description'    => sprintf(
                'Модератор (ID: %d) добавил заметку пользователю ID: %d. Текст: %s',
                $this->moderatorId,
                $this->targetUserId,
                $this->notePreview
            ),
        ];
    }
}