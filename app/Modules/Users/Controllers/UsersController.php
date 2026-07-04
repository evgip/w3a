<?php

declare(strict_types=1);

namespace App\Modules\Users\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Modules\Users\Services\UserService;
use App\Modules\Users\Services\AvatarService;
use App\Modules\Users\Models\User;
use App\Modules\Auth\Services\Auth;

/**
 * Контроллер для управления профилями пользователей и настройками аккаунта.
 * 
 * Отвечает за:
 * - Отображение публичных профилей пользователей
 * - Управление настройками профиля (email, bio, аватар, уведомления)
 * - Смену пароля авторизованным пользователем
 * 
 * Все действия (кроме profile) требуют авторизации через сессию.
 * Зависимости (UserService, AvatarService) получаются из контейнера через методы-геттеры.
 */
class UsersController extends Controller
{
    /**
     * Получить сервис для работы с пользователями из контейнера.
     */
    private function getUserService(): UserService
    {
        return $this->service(UserService::class);
    }

    /**
     * Получить сервис для работы с аватарами из контейнера.
     */
    private function getAvatarService(): AvatarService
    {
        return $this->service(AvatarService::class);
    }

    /**
     * Отображение всех участников (GET /users).
     */
    public function index() {
        return true;
    }

    /**
     * Отображение публичного профиля пользователя (GET /user/{username}).
     * 
     * @param string $username Имя пользователя (username) — обязательный параметр маршрута
     * @return void
     */
    public function profile(string $username): void
    {
        // ✅ Получаем модель из контейнера вместо new
        $userModel = $this->container->get(User::class);
        
        // Ищем пользователя по username (с обрезкой пробелов)
        $user = $userModel->findBy('username', trim($username));

        if (!$user) {
            // Пользователь не найден — показываем 404
            $errorController = "App\\Modules\\Errors\\Controllers\\ErrorsController";
            if (class_exists($errorController)) {
                // ✅ Создаём через контейнер с инъекцией зависимостей
                $controller = $this->container->make($errorController);
                $controller->notFound("Пользователь <i>{$username}</i> не найден.");
                exit;
            }
            die("<h1>404 Errors</h1>");
        }

        // Загружаем дополнительные данные профиля (bio, аватар)
        $profile = $userModel->getProfile((int)$user['id']);
        $user['bio'] = $profile['bio'] ?? null;
        $user['avatar'] = $profile['avatar'] ?? null;

        // Проверяем, забанен ли пользователь
        $banInfo = $userModel->getBanInfo((int)$user['id']);
        $user['is_banned'] = $banInfo !== null;
        $user['ban_reason'] = $banInfo['reason'] ?? null;
        $user['banned_at'] = $banInfo['created_at'] ?? null;
        $user['expires_at'] = $banInfo['expires_at'] ?? null;

        // Загружаем статистику (количество историй и комментариев)
        $stats = $userModel->getProfileStats((int)$user['id']);
        
        // Загружаем карму пользователя
        $userKarma = $userModel->getUserKarma((int)$user['id']);

        // Рендерим шаблон профиля с данными
        $this->render('profile', [
            'title' => 'Профиль пользователя ' . e($user['username']),
            'profileUser' => $user,
            'storiesCount' => $stats['stories_count'] ?? 0,
            'commentsCount' => $stats['comments_count'] ?? 0,
            'userKarma' => $userKarma ?? 0
        ]);
    }

    /**
     * Отображение страницы настроек профиля (GET /account/settings).
     * 
     * @return void
     */
    public function settings(): void
    {
        $userId = Auth::id();
        
        // Загружаем пользователя с данными профиля
        $user = $this->getUserService()->getUserWithProfile($userId);
        if (!$user) {
            redirect('/');
        }
        
        // Загружаем настройки через сервис
        $settings = $this->getUserService()->getUserSettings($userId);
        
        // Рендерим страницу
        $this->render('settings', [
            'title' => 'Настройки профиля',
            'user' => $user,
            'settings' => $settings,
            'request' => $this->request
        ]);
    }

    /**
     * Обработка обновления настроек профиля (POST /account/settings).
     * 
     * @return void
     */
    public function updateSettings(): void
    {
        // Получаем ID текущего пользователя
        $userId = Auth::id();
        
        // Загружаем текущие данные пользователя
        $user = $this->getUserService()->getUserWithProfile($userId);

        if (!$user) {
            redirect('/');
        }

        // Получаем данные из формы
        $email = trim($this->request->getParams('email'));
        $bio = trim($this->request->getParams('bio'));
        $oldAvatarFilename = $user['avatar'];
        $newAvatarFilename = $oldAvatarFilename;

        // Обновление email
        if ($email !== $user['email']) {
            if (!$this->getUserService()->updateEmail($userId, $email)) {
                redirect(route('account.settings'));
            }
        }

        // Загрузка нового аватара
        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedAvatar = $this->getAvatarService()->handleUpload(
                $_FILES['avatar_file'], 
                $oldAvatarFilename
            );
            
            if ($uploadedAvatar) {
                $newAvatarFilename = $uploadedAvatar;
            }
        }

        // Обновление профиля
        $this->getUserService()->updateProfile($userId, [
            'bio' => $bio,
            'avatar' => $newAvatarFilename
        ]);

        // Обновление настроек уведомлений
        $this->getUserService()->updateSettings($userId, [
            'notify_on_reply' => $this->request->getParams('notify_on_reply') ? 1 : 0,
            'notify_on_story_comment' => $this->request->getParams('notify_on_story_comment') ? 1 : 0,
            'notify_on_mention' => $this->request->getParams('notify_on_mention') ? 1 : 0,
            'notify_on_message' => $this->request->getParams('notify_on_message') ? 1 : 0,
            'email_notifications' => $this->request->getParams('email_notifications') ? 1 : 0,
        ]);

        // Обновляем аватар в сессии (для отображения в шапке сайта)
        // ✅ Используем Session через контейнер
        $session = $this->container->get(Session::class);
        $session->set('user_avatar', $newAvatarFilename);

        // Показываем flash-сообщение об успехе
        $session->flash('success', 'Настройки сохранены.');
        
        // Редирект обратно на страницу настроек
        redirect(route('account.settings'));
    }

    /**
     * Обработка смены пароля (POST /account/password).
     * 
     * @return void
     */
    public function updatePassword(): void
    {
        $userId = Auth::id();
        
        $currentPassword = $this->request->getParams('current_password');
        $newPassword = $this->request->getParams('new_password');

        // ✅ Используем Session через контейнер
        $session = $this->container->get(Session::class);

        // Валидация длины пароля
        if (strlen($newPassword) < 6) {
            $session->flash('error', 'Пароль должен быть не менее 6 символов.');
            redirect(route('account.settings'));
        }

        // Попытка смены пароля
        $this->getUserService()->changePassword($userId, $currentPassword, $newPassword);

        // Редирект обратно на страницу настроек
        redirect(route('account.settings'));
    }
}