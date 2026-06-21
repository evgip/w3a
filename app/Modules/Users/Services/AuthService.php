<?php

declare(strict_types=1);

namespace App\Modules\Users\Services;

use App\Modules\Users\Models\User;
use App\Core\Session as AppCoreSession;

class AuthService
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Аутентифицировать пользователя
     */
    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->userModel->findBy('email', $email);

        if (!$user || !password_verify($password, $user['password'])) {
            return null;
        }

        if ((int)$user['is_active'] !== 1) {
            AppCoreSession::setFlash('error', 'Аккаунт не активирован.');
            return null;
        }

        return $user;
    }

    /**
     * Зарегистрировать нового пользователя
     */
    public function register(string $username, string $email, string $password): ?int
    {
        // Проверка уникальности email
        if ($this->userModel->findBy('email', $email)) {
            AppCoreSession::setFlash('error', 'Email уже зарегистрирован.');
            return null;
        }

        // Проверка уникальности username
        if ($this->userModel->findByName($username)) {
            AppCoreSession::setFlash('error', 'Имя пользователя уже занято.');
            return null;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $userId = $this->userModel->create([
            'username' => $username,
            'email' => $email,
            'password' => $hashedPassword,
            'role' => 'user'
        ]);

        if ($userId > 0) {
            // Инициализируем профиль и настройки
            $this->userModel->updateProfile($userId, ['bio' => null, 'avatar' => null]);
            $this->userModel->updateSettings($userId, [
                'notify_on_reply' => 1,
                'notify_on_story_comment' => 1,
                'email_notifications' => 0
            ]);
        }

        return $userId > 0 ? $userId : null;
    }

    /**
     * Создать сессию для пользователя
     */
    public function createSession(array $user): void
    {
        $profile = $this->userModel->getProfile((int)$user['id']);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['username'];
        $_SESSION['user_avatar'] = $profile['avatar'] ?? null;
        $_SESSION['user_role'] = $user['role'] ?? 'user';
        $_SESSION['last_activity_time'] = time();
    }

    /**
     * Завершить сессию
     */
    public function logout(): void
    {
        session_destroy();
        $_SESSION = [];
    }
}