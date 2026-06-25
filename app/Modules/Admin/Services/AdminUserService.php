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
 */
class AdminUserService
{
    private User $userModel;
    private AdminUser $adminUserModel;
    private Notification $notificationModel;

    public function __construct()
    {
        $this->userModel = new User();
        $this->adminUserModel = new AdminUser();
        $this->notificationModel = new Notification();
    }

    /**
     * Получить список всех пользователей (включая удалённых).
     */
    public function getAllUsers(): array
    {
        return $this->userModel->getAllUsersWithBanStatus(withTrashed: true);
    }

    /**
     * Получить расширенный список пользователей для админки.
     */
    public function getAdminUsersList(int $limit = 100): array
    {
        return $this->adminUserModel->getAdminUsersList($limit);
    }

    /**
     * Отправить пользователя в архив (soft delete).
     *
     * @return bool true если успешно, false если попытка удалить себя
     */
    public function archiveUser(int $userId, int $currentAdminId): bool
    {
        if ($userId === $currentAdminId) {
            Session::setFlash('error', 'Вы не можете отправить в архив собственный аккаунт!');
            return false;
        }

        $this->userModel->delete($userId);
        return true;
    }

    /**
     * Восстановить пользователя из архива.
     */
    public function restoreUser(int $userId): void
    {
        $this->userModel->restore($userId);
    }

    /**
     * Обновить данные профиля пользователя.
     */
    public function updateUserProfile(int $userId, array $data): void
    {
        // Обновляем основные данные пользователя
        $this->userModel->update($userId, [
            'email' => trim($data['email'] ?? ''),
            'role' => trim($data['role'] ?? 'user'),
        ]);

        // Обновляем профиль (bio, avatar)
        $this->userModel->updateProfile($userId, [
            'bio' => trim($data['bio'] ?? ''),
        ]);
    }

    /**
     * Переключить статус активации пользователя.
     *
     * @return int Новый статус (0 = заблокирован, 1 = активен, -1 = не найден)
     */
    public function toggleUserStatus(int $targetUserId, int $currentAdminId): int
    {
        if ($targetUserId === $currentAdminId) {
            Session::setFlash('error', 'Вы не можете заблокировать собственный административный аккаунт.');
            return -2; // Специальный код для "попытка заблокировать себя"
        }

        $newStatus = $this->adminUserModel->toggleActivationStatus($targetUserId);

        if ($newStatus === -1) {
            return -1; // Пользователь не найден
        }

        $user = $this->adminUserModel->find($targetUserId);

        // ✅ Отправляем уведомление пользователю
        // $notificationModel = new \App\Modules\Notifications\Models\Notification();

        // Отправляем уведомление пользователю
        if ($newStatus === 0) {
            // ✅ Деактивация — используем семантичный метод
            // $notificationModel->createDeactivatedNotification($targetUserId, $currentAdminId);

            Audit::log(
                'admin.user_suspended',
                "Администратор принудительно ЗАБЛОКИРОВАЛ аккаунт: {$user['username']} (ID: {$targetUserId})",
                'admin'
            );
        } else {
            // ✅ Активация — используем семантичный метод
            //$notificationModel->createActivatedNotification($targetUserId, $currentAdminId);

            Audit::log(
                'admin.user_unsuspended',
                "Администратор СНЯЛ блокировку с аккаунта: {$user['username']} (ID: {$targetUserId})",
                'admin'
            );
        }

        return $newStatus;
    }

    /**
     * Удалить аватар пользователя.
     */
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

        // Удаляем физический файл
        if (file_exists($avatarPath)) {
            unlink($avatarPath);
        }

        // Удаляем пустую подпапку шардирования
        if (is_dir($oldFolderDir)) {
            $remainingFiles = array_diff(scandir($oldFolderDir), ['.', '..']);
            if (empty($remainingFiles)) {
                rmdir($oldFolderDir);
            }
        }

        $this->userModel->update($userId, ['avatar' => null]);

        Audit::log(
            'admin.avatar_deleted',
            "Администратор принудительно удалил аватар пользователя ID: {$userId}",
            'admin'
        );

        // Уведомляем пользователя
        $this->notificationModel->create([
            'user_id' => $userId,
            'type' => 'danger',
            'message' => 'Ваш профильный аватар был принудительно удален администратором из-за нарушения правил сообщества.'
        ]);

        return true;
    }

    /**
     * Найти пользователя по ID с данными профиля.
     */
    public function findUser(int $userId): ?array
    {
        $user = $this->userModel->find($userId);

        if (!$user) {
            return null;
        }

        // Получаем данные профиля (bio, avatar)
        $profile = $this->userModel->getProfile($userId);
        $user['bio'] = $profile['bio'] ?? null;
        $user['avatar'] = $profile['avatar'] ?? null;

        return $user;
    }
}
