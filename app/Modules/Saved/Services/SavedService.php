<?php

declare(strict_types=1);

namespace App\Modules\Saved\Services;

use App\Modules\Saved\Models\SavedStory;

/**
 * Сервис для управления закладками (сохранёнными историями).
 * Не зависит от HTTP или сессий.
 */
class SavedService
{
    private SavedStory $savedStory;

    public function __construct(SavedStory $savedStory)
    {
        $this->savedStory = $savedStory;
    }

    /**
     * Переключает состояние закладки и возвращает новый статус.
     *
     * @return bool true, если история добавлена в закладки; false, если удалена
     */
    public function toggle(int $userId, int $storyId): bool
    {
        if ($this->savedStory->isSaved($userId, $storyId)) {
            $this->savedStory->unsave($userId, $storyId);
            return false; // Теперь не в закладках
        }
        
        $this->savedStory->save($userId, $storyId);
        return true; // Теперь в закладках
    }

    /**
     * Проверяет, сохранена ли история пользователем.
     */
    public function isSaved(int $userId, int $storyId): bool
    {
        return $this->savedStory->isSaved($userId, $storyId);
    }
}