<?php

declare(strict_types=1);

namespace App\Modules\Muted\Services;

use App\Modules\Muted\Models\MutedUser;
use App\Modules\Muted\Exceptions\MuteValidationException;

/**
 * Сервис для управления игнорируемыми пользователями (mute).
 * Не зависит от HTTP или сессий, выполняет только бизнес-логику.
 */
class MuteService
{
    private MutedUser $mutedUser;

    public function __construct(MutedUser $mutedUser)
    {
        $this->mutedUser = $mutedUser;
    }

    /**
     * Переключает статус игнорирования пользователя.
     *
     * @throws MuteValidationException Если пользователь пытается игнорировать самого себя
     * @return bool true, если пользователь добавлен в игнор; false, если удалён из игнора
     */
    public function toggle(int $userId, int $targetUserId): bool
    {
        if ($userId === $targetUserId) {
            throw new MuteValidationException('Нельзя игнорировать самого себя');
        }

        if ($this->mutedUser->isMuted($userId, $targetUserId)) {
            $this->mutedUser->unmute($userId, $targetUserId);
            return false; // Теперь не в игноре
        }

        $this->mutedUser->mute($userId, $targetUserId);
        return true; // Теперь в игноре
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
