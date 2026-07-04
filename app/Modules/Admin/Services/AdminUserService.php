<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Modules\Users\Models\User;
use App\Modules\Users\Models\Notification;
use App\Modules\Admin\Models\AdminUser;
use App\Core\Session;
use App\Core\Audit;

/**
 * Сервис для административного управления пользователями.
 * 
 * ✅ ИЗМЕНЕНО: Все зависимости обязательны и внедряются через конструктор.
 */
class AdminUserService
{
    private User $userModel;
    private AdminUser $adminUserModel;
    private Notification $notificationModel;
    private Session $session;
    private Audit $audit;

    public function __construct(
        User $userModel,
        AdminUser $adminUserModel,
        Notification $notificationModel,
        Session $session,
        Audit $audit
    ) {
        $this->userModel = $userModel;
        $this->adminUserModel = $adminUserModel;
        $this->notificationModel = $notificationModel;
        $this->session = $session;
        $this->audit = $audit;
    }

    public function getAllUsers(): array
    {
        return $this->userModel->getAllUsersWithBanStatus(withTrashed: true);
    }

    public function getAdminUsersList(int $limit = 100): array
    {
        return $this->adminUserModel->getAdminUsersList($limit);
    }

    public function archiveUser(int $userId, int $currentAdminId): bool
    {
        if ($userId === $currentAdminId) {
            $this->session->flash('error', 'Вы не можете отправить в архив собственный аккаунт!');
            return false;
        }

        $this->userModel->delete($userId);
        return true;
    }

    public function restoreUser(int $userId): void
    {
        $this->userModel->restore($userId);
    }

    public function updateUserProfile(int $userId, array $data): void
    {
        $this->userModel->update($userId, [
            'email' => trim($data['email'] ?? ''),
            'role' => trim($data['role'] ?? 'user'),
        ]);

        $this->userModel->updateProfile($userId, [
            'bio' => trim($data['bio'] ?? ''),
        ]);
    }

    public function toggleUserStatus(int $targetUserId, int $currentAdminId): int
    {
        if ($targetUserId === $currentAdminId) {
            $this->session->flash('error', 'Вы не можете заблокировать собственный административный аккаунт.');
            return -2;
        }

        $newStatus = $this->adminUserModel->toggleActivationStatus($targetUserId);

        if ($newStatus === -1) {
            return -1;
        }

        $user = $this->adminUserModel->find($targetUserId);

        if ($newStatus === 0) {
            $this->audit->log(
                'admin.user_suspended',
                "Администратор принудительно ЗАБЛОКИРОВАЛ аккаунт: {$user['username']} (ID: {$targetUserId})",
                'admin'
            );
        } else {
            $this->audit->log(
                'admin.user_unsuspended',
                "Администратор СНЯЛ блокировку с аккаунта: {$user['username']} (ID: {$targetUserId})",
                'admin'
            );
        }

        return $newStatus;
    }

    public function deleteUserAvatar(int $userId): bool
    {
        $user = $this->userModel->find($userId);

        if (!$user || empty($user['avatar'])) {
            return false;
        }

        $subFolder = substr($user['avatar'], 0, 2);
        $baseUploadDir = dirname(__DIR__, 3) . '/public/uploads/avatars';
        $oldFolderDir = $baseUploadDir . '/' . $subFolder;
        $avatarPath = $oldFolderDir . '/' . $user['avatar'];

        if (file_exists($avatarPath)) {
            unlink($avatarPath);
        }

        if (is_dir($oldFolderDir)) {
            $remainingFiles = array_diff(scandir($oldFolderDir), ['.', '..']);
            if (empty($remainingFiles)) {
                rmdir($oldFolderDir);
            }
        }

        $this->userModel->update($userId, ['avatar' => null]);

        $this->audit->log(
            'admin.avatar_deleted',
            "Администратор принудительно удалил аватар пользователя ID: {$userId}",
            'admin'
        );

        $this->notificationModel->create([
            'user_id' => $userId,
            'type' => 'danger',
            'message' => 'Ваш профильный аватар был принудительно удален администратором из-за нарушения правил сообщества.'
        ]);

        return true;
    }

    public function findUser(int $userId): ?array
    {
        $user = $this->userModel->find($userId);

        if (!$user) {
            return null;
        }

        $profile = $this->userModel->getProfile($userId);
        $user['bio'] = $profile['bio'] ?? null;
        $user['avatar'] = $profile['avatar'] ?? null;

        return $user;
    }
}