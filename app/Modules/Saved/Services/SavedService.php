<?php

declare(strict_types=1);

namespace App\Modules\Saved\Services;

use App\Modules\Saved\Models\SavedStory;
use App\Core\Session;

class SavedService
{
    private SavedStory $savedStory;
    private Session $session;

    public function __construct(SavedStory $savedStory, Session $session)
    {
        $this->savedStory = $savedStory;
        $this->session = $session;
    }

    public function toggle(int $userId, int $storyId): bool
    {
        if ($this->savedStory->isSaved($userId, $storyId)) {
            $this->savedStory->unsave($userId, $storyId);
            $this->session->flash('success', 'История удалена из закладок');
            return false;
        }
        
        $this->savedStory->save($userId, $storyId);
        $this->session->flash('success', 'История добавлена в закладки');
        return true;
    }

    public function isSaved(int $userId, int $storyId): bool
    {
        return $this->savedStory->isSaved($userId, $storyId);
    }
}