<?php

declare(strict_types=1);

namespace App\Core\Events;

/**
 * Событие добавления модераторской заметки о пользователе.
 * 
 * Отправляется после успешного создания заметки.
 * Всегда попадает в категорию 'moderation'.
 */
class ModNoteAdded extends Event
{
    /**
     * @param int $moderatorId ID модератора, который добавил заметку
     * @param int $targetUserId ID пользователя, к которому относится заметка
     * @param string $noteText Текст заметки
     */
    public function __construct(
        private int $moderatorId,
        private int $targetUserId,
        private string $noteText
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
        $noteText = trim($this->noteText) !== '' 
            ? $this->noteText 
            : '(пустая заметка)';

        $description = sprintf(
            'Модератор (ID: %d) добавил заметку о пользователе ID: %d: "%s"',
            $this->moderatorId,
            $this->targetUserId,
            $noteText
        );

        return [
            'moderator_id' => $this->moderatorId,
            'target_user_id' => $this->targetUserId,
            'note' => $this->noteText,
            'description' => $description,
        ];
    }
}