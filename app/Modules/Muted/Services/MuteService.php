<?php

declare(strict_types=1);

namespace App\Modules\Muted\Services;

use App\Modules\Muted\Models\MutedUser;
use App\Core\Session;

class MuteService
{
    private MutedUser $mutedUser;
    private Session $session;

    public function __construct(MutedUser $mutedUser, Session $session)
    {
        $this->mutedUser = $mutedUser;
        $this->session = $session;
    }

    /**
     * Переключить мьют
     */
    public function toggle(int $userId, int $targetUserId): bool
    {
        if ($userId === $targetUserId) {
            $this->session->flash('error', 'Нельзя игнорировать самого себя');
            return false;
        }

        if ($this->mutedUser->isMuted($userId, $targetUserId)) {
            $this->mutedUser->unmute($userId, $targetUserId);
            $this->session->flash('success', 'Пользователь разблокирован');
            return false;
        }

        $this->mutedUser->mute($userId, $targetUserId);
        $this->session->flash('success', 'Пользователь игнорирован. Его истории и комментарии больше не будут вам показываться.');
        return true;
    }

    public function isMuted(int $userId, int $targetUserId): bool
    {
        return $this->mutedUser->isMuted($userId, $targetUserId);
    }

    public function getMutedList(int $userId): array
    {
        return $this->mutedUser->getMutedList($userId);
    }

    public function getMutedUserIds(int $userId): array
    {
        return $this->mutedUser->getMutedUserIds($userId);
    }
}
