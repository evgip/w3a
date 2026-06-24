<?php

declare(strict_types=1);

namespace App\Modules\Users\Services;

use App\Modules\Users\Models\User;
use App\Core\Session as AppCoreSession;

class UserService
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Получить пользователя с профилем и настройками
     */
    public function getUserWithProfile(int $userId): ?array
    {
        $user = $this->userModel->find($userId);
        if (!$user) return null;

        $profile = $this->userModel->getProfile($userId);
        $user['bio'] = $profile['bio'] ?? null;
        $user['avatar'] = $profile['avatar'] ?? null;

        $settings = $this->userModel->getSettings($userId);
        $user['notify_on_reply'] = $settings['notify_on_reply'] ?? 1;
        $user['notify_on_story_comment'] = $settings['notify_on_story_comment'] ?? 1;
        $user['email_notifications'] = $settings['email_notifications'] ?? 0;

        return $user;
    }

    /**
     * Обновить email пользователя
     */
    public function updateEmail(int $userId, string $newEmail): bool
    {
        // Проверка уникальности
        $existingUser = $this->userModel->findBy('email', $newEmail);
        if ($existingUser && (int)$existingUser['id'] !== $userId) {
            AppCoreSession::setFlash('error', 'Этот Email уже занят.');
            return false;
        }

        return $this->userModel->update($userId, ['email' => $newEmail]);
    }

    /**
     * Обновить профиль (bio, avatar)
     */
    public function updateProfile(int $userId, array $data): bool
    {
        return $this->userModel->updateProfile($userId, $data);
    }

    /**
     * Обновить настройки уведомлений
     */
    public function updateSettings(int $userId, array $data): bool
    {
        return $this->userModel->updateSettings($userId, [
            'notify_on_reply' => $data['notify_on_reply'] ?? 0,
            'notify_on_story_comment' => $data['notify_on_story_comment'] ?? 0,
            'email_notifications' => $data['email_notifications'] ?? 0,
        ]);
    }

    /**
     * Сменить пароль
     */
	public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
	{
		$user = $this->userModel->find($userId);
		if (!$user) {
			AppCoreSession::setFlash('error', 'Пользователь не найден.');
			return false;
		}

		if (!password_verify($currentPassword, $user['password'])) {
			AppCoreSession::setFlash('error', 'Текущий пароль введён неверно.');
			return false;
		}

		$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
		$success = $this->userModel->update($userId, ['password' => $hashedPassword]);
		
		if ($success) {
			AppCoreSession::setFlash('success', 'Пароль успешно изменён.');
		}
		
		return $success;
	}

    /**
     * Получить список всех пользователей с информацией о бане
     */
    public function getAllUsers(): array
    {
        return $this->userModel->getAllUsersWithBanStatus(withTrashed: true);
    }
}