<?php

declare(strict_types=1);

namespace App\Modules\Users\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\Exceptions\NotFoundException;
use App\Modules\Users\Services\UserService;
use App\Modules\Users\Services\AvatarService;
use App\Modules\Users\Models\User;

/**
 * Контроллер для управления профилями пользователей и настройками аккаунта.
 * 
 * Обрабатывает:
 * - Публичный профиль пользователя
 * - Настройки профиля (email, bio, avatar)
 * - Смену пароля
 * - Управление уведомлениями
 */
class UsersController extends Controller
{
    private function getUserService(): UserService
    {
        return $this->service(UserService::class);
    }

    private function getAvatarService(): AvatarService
    {
        return $this->service(AvatarService::class);
    }

    /**
     * Отображение всех участников (GET /users).
     */
    public function index(): void
    {
        // TODO: Реализовать список пользователей
        $this->render('index', [
            'title' => 'Участники',
        ]);
    }

    /**
     * Отображение публичного профиля пользователя (GET /user/{username}).
     */
    public function profile(string $username): void
    {
        $user = $this->getUserByUsername(trim($username));

        // Загружаем дополнительные данные профиля
        $profile = $user['profile'] ?? [];
        $user['bio'] = $profile['bio'] ?? null;
        $user['avatar'] = $profile['avatar'] ?? null;

        // Загружаем информацию о бане
        $banInfo = $this->container->get(User::class)->getBanInfo((int)$user['id']);
        $user['is_banned'] = $banInfo !== null;
        $user['ban_reason'] = $banInfo['reason'] ?? null;
        $user['banned_at'] = $banInfo['created_at'] ?? null;
        $user['expires_at'] = $banInfo['expires_at'] ?? null;

        // Загружаем статистику
        $stats = $this->container->get(User::class)->getProfileStats((int)$user['id']);
        $userKarma = $this->container->get(User::class)->getUserKarma((int)$user['id']);

        $userContext = $this->getUserContext();

        // Передаём статус мьюта в шаблон
        $isMuted = false;
        if ($userContext['isLoggedIn'] && (int)$user['id'] !== $userContext['id']) {
            $muteService = $this->service(\App\Modules\Muted\Services\MuteService::class);
            $isMuted = $muteService->isMuted($userContext['id'], (int)$user['id']);
        }

        $this->render('profile', [
            'title' => 'Профиль пользователя ' . e($user['username']),
            'profileUser' => $user,
            'storiesCount' => $stats['stories_count'] ?? 0,
            'commentsCount' => $stats['comments_count'] ?? 0,
            'userKarma' => $userKarma ?? 0,
            'isMuted' => $isMuted,
        ]);
    }

    /**
     * Отображение страницы настроек профиля (GET /account/settings).
     */
    public function settings(): void
    {
        $userContext = $this->getUserContext();
        $user = $this->getUserWithProfileOrRedirect($userContext['id']);

        if ($user === null) {
            return;
        }

        $settings = $this->getUserService()->getUserSettings($userContext['id']);

        $this->render('settings', [
            'title' => 'Настройки профиля',
            'user' => $user,
            'settings' => $settings,
            'request' => $this->request
        ]);
    }

    /**
     * Обработка обновления настроек профиля (POST /account/settings).
     */
    public function updateSettings(): void
    {
        $userContext = $this->getUserContext();
        $user = $this->getUserWithProfileOrRedirect($userContext['id']);

        if ($user === null) {
            return;
        }

        $email = trim($this->request->getParams('email', ''));
        $bio = trim($this->request->getParams('bio', ''));
        $oldAvatarFilename = $user['avatar'] ?? '';
        $newAvatarFilename = $oldAvatarFilename;

        // Обновление email
        if ($email !== $user['email']) {
            if (!$this->getUserService()->updateEmail($userContext['id'], $email)) {
                $this->redirectWithMessage(route('account.settings'), 'Не удалось обновить email', 'error');
                return;
            }
        }

        $avatarFile = $this->request->file('avatar_file');
        if ($avatarFile && $avatarFile['error'] === UPLOAD_ERR_OK) {
            $uploadedAvatar = $this->getAvatarService()->handleUpload(
                $avatarFile,
                $oldAvatarFilename
            );

            if ($uploadedAvatar) {
                $newAvatarFilename = $uploadedAvatar;
            }
        }

        // Обновление профиля
        $this->getUserService()->updateProfile($userContext['id'], [
            'bio' => $bio,
            'avatar' => $newAvatarFilename
        ]);

        // Обновление настроек уведомлений
        $this->getUserService()->updateSettings($userContext['id'], [
            'notify_on_reply' => $this->request->getParams('notify_on_reply') ? 1 : 0,
            'notify_on_story_comment' => $this->request->getParams('notify_on_story_comment') ? 1 : 0,
            'notify_on_mention' => $this->request->getParams('notify_on_mention') ? 1 : 0,
            'notify_on_message' => $this->request->getParams('notify_on_message') ? 1 : 0,
            'email_notifications' => $this->request->getParams('email_notifications') ? 1 : 0,
        ]);

        // Обновляем аватар в сессии
        $session = $this->container->get(Session::class);
        $session->set('user_avatar', $newAvatarFilename);

        $this->redirectWithMessage(route('account.settings'), 'Настройки сохранены.', 'success');
    }

    /**
     * Обработка смены пароля (POST /account/password).
     */
    public function updatePassword(): void
    {
        $userContext = $this->getUserContext();

        $currentPassword = $this->request->getParams('current_password', '');
        $newPassword = $this->request->getParams('new_password', '');

        // Валидация длины пароля
        if (strlen($newPassword) < 6) {
            $this->redirectWithMessage(route('account.settings'), 'Пароль должен быть не менее 6 символов.', 'error');
            return;
        }

        // Попытка смены пароля
        $success = $this->getUserService()->changePassword($userContext['id'], $currentPassword, $newPassword);

        if ($success) {
            $this->redirectWithMessage(route('account.settings'), 'Пароль успешно изменён.', 'success');
        } else {
            $this->redirectWithMessage(route('account.settings'), 'Не удалось изменить пароль. Проверьте текущий пароль.', 'error');
        }
    }

    // =========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    /**
     * Получить пользователя по username или выбросить 404
     */
    private function getUserByUsername(string $username): array
    {
        $userModel = $this->container->get(User::class);
        $user = $userModel->findBy('username', $username);

        if (!$user) {
            throw new NotFoundException("Пользователь <i>{$username}</i> не найден.");
        }

        // Загружаем профиль
        $profile = $userModel->getProfile((int)$user['id']);
        $user['profile'] = $profile;

        return $user;
    }

    /**
     * Получить пользователя с профилем или сделать редирект
     */
    private function getUserWithProfileOrRedirect(int $userId): ?array
    {
        $user = $this->getUserService()->getUserWithProfile($userId);

        if (!$user) {
            $this->redirect('/');
            return null;
        }

        return $user;
    }
}
