<?php

declare(strict_types=1);

namespace App\Core\Events;

/**
 * Событие обработки жалобы (флага).
 * 
 * Отправляется после того, как модератор рассмотрел жалобу и принял решение.
 * Всегда попадает в категорию 'moderation'.
 */
class FlagResolved extends Event
{
    /**
     * @param int $flagId ID обработанной жалобы
     * @param int $resolvedByUserId ID модератора, который рассмотрел жалобу
     * @param string $resolution Решение по жалобе (одобрить, отклонить и т.д.)
     */
    public function __construct(
        private int $flagId,
        private int $resolvedByUserId,
        private string $resolution
    ) {}

    public function getName(): string
    {
        return 'flag.resolved';
    }

    public function getCategory(): string
    {
        return 'moderation';
    }

    public function getData(): array
    {
        $resolutionText = trim($this->resolution) !== '' 
            ? $this->resolution 
            : 'Без указания решения';

        $description = sprintf(
            'Модератор (ID: %d) рассмотрел жалобу ID: %d. Решение: %s',
            $this->resolvedByUserId,
            $this->flagId,
            $resolutionText
        );

        return [
            'flag_id' => $this->flagId,
            'resolved_by' => $this->resolvedByUserId,
            'resolution' => $this->resolution,
            'description' => $description,
        ];
    }
}