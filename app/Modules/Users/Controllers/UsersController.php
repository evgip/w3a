<?php

declare(strict_types=1);

namespace App\Modules\Users\Controllers;

use App\Core\Controller;
use App\Core\Request as AppCoreRequest;
use App\Core\Session as AppCoreSession;
use App\Core\Auth;
use App\Modules\Users\Services\UserService;
use App\Modules\Users\Services\AuthService;
use App\Modules\Users\Services\AvatarService;

class UsersController extends Controller
{
    private ?UserService $userService = null;
    private ?AuthService $authService = null;
    private ?AvatarService $avatarService = null;

    /**
     * Получить экземпляр сервиса работы с пользователями (ленивая инициализация).
     *
     * @return UserService
     */
    private function getUserService(): UserService
    {
        if ($this->userService === null) {
            $this->userService = new UserService();
        }
        return $this->userService;
    }

    /**
     * Получить экземпляр сервиса аутентификации (ленивая инициализация).
     *
     * @return AuthService
     */
    private function getAuthService(): AuthService
    {
        if ($this->authService === null) {
            $this->authService = new AuthService();
        }
        return $this->authService;
    }

    /**
     * Получить экземпляр сервиса работы с аватарами (ленивая инициализация).
     *
     * @return AvatarService
     */
    private function getAvatarService(): AvatarService
    {
        if ($this->avatarService === null) {
            $this->avatarService = new AvatarService();
        }
        return $this->avatarService;
    }

    /**
     * Обработка входа пользователя в систему (POST /login).
     * Валидирует CSRF-токен, проверяет email/пароль, создаёт сессию.
     *
     * @return void
     */
    public function login(): void
    {
        $request = new AppCoreRequest();
        $request->validateCsrf();

        $email = trim($request->getParams('email'));
        $password = $request->getParams('password');

        $user = $this->getAuthService()->authenticate($email, $password);
        if (!$user) {
            header('Location: /login');
            exit;
        }

        $this->getAuthService()->createSession($user);
        AppCoreSession::setFlash('success', 'Добро пожаловать!');
        header('Location: /');
        exit;
    }

    /**
     * Отображение формы логина (GET /login)
     */
    public function showLoginForm()
    {
        $request = new AppCoreRequest();
        
        // Рендерим шаблон login.php из папки Views модуля Users
        $this->render('login', [
            'title' => 'Авторизация',
            'request' => $request // Передаем объект запроса для вывода CSRF-поля
        ]);
    }

    /**
     * Display the registration form (GET /register)
     */
	public function showRegisterForm(): void
	{
		$request = new AppCoreRequest();
		
		// Получаем старые значения из сессии (если есть)
		$old = \App\Core\Session::get('old_input', []);
		\App\Core\Session::delete('old_input'); // Очищаем после использования
		
		$this->render('register', [
			'title' => 'Регистрация нового пользователя',
			'request' => $request,
			'old' => $old
		]);
	}

    /**
     * Обработка регистрации нового пользователя (POST /register).
     * Валидирует CSRF-токен, создаёт пользователя, инициализирует профиль и настройки.
     *
     * @return void
     */
    public function register(): void
    {
        $request = new AppCoreRequest();
        $request->validateCsrf();

        $username = trim($request->getParams('username'));
        $email = trim($request->getParams('email'));
        $password = $request->getParams('password');

        $userId = $this->getAuthService()->register($username, $email, $password);
        if (!$userId) {
            header('Location: /register');
            exit;
        }

        AppCoreSession::setFlash('success', 'Регистрация успешна! Проверьте почту.');
        header('Location: /login');
        exit;
    }

    /**
     * Выход пользователя из системы (POST /logout).
     * Уничтожает сессию и перенаправляет на главную страницу.
     *
     * @return void
     */
    public function logout(): void
    {
        $this->getAuthService()->logout();
        header('Location: /');
        exit;
    }

    /**
     * Отображение публичного профиля пользователя (GET /user/{username}).
     * Загружает данные пользователя, профиль, информацию о бане, статистику и карму.
     *
     * @param string $username Имя пользователя (username)
     * @return void
     */
    public function profile(string $username): void
    {
        $userModel = new \App\Modules\Users\Models\User();
        $user = $userModel->findBy('username', trim($username));

        if (!$user) {
			$errorController = "App\\Modules\\Errors\\Controllers\\ErrorsController";
			if (class_exists($errorController)) {
				(new $errorController())->notFound("Пользователь <i>{$username}</i> не найден.");
				exit;
			}
			die("<h1>404 Errors</h1>");
        }

        $profile = $userModel->getProfile((int)$user['id']);
        $user['bio'] = $profile['bio'] ?? null;
        $user['avatar'] = $profile['avatar'] ?? null;

        $banInfo = $userModel->getBanInfo((int)$user['id']);
        $user['is_banned'] = $banInfo !== null;
        $user['ban_reason'] = $banInfo['reason'] ?? null;
        $user['banned_at'] = $banInfo['created_at'] ?? null;
        $user['expires_at'] = $banInfo['expires_at'] ?? null;

        $stats = $userModel->getProfileStats((int)$user['id']);
        $userKarma = $userModel->getUserKarma((int)$user['id']);

        $this->render('profile', [
            'title' => 'Профиль пользователя ' . e($user['username']),
            'profileUser' => $user,
            'storiesCount' => $stats['stories_count'],
            'commentsCount' => $stats['comments_count'],
            'userKarma' => $userKarma
        ]);
    }

    /**
     * Отображение страницы настроек профиля (GET /account/settings).
     * Требует авторизации. Загружает данные пользователя, профиля, настроек и уведомлений.
     *
     * @return void
     */
    public function settings(): void
    {
        $userId = (int)$_SESSION['user_id'];
        $user = $this->getUserService()->getUserWithProfile($userId);

        if (!$user) {
            header('Location: /');
            exit;
        }

        $notifModel = new \App\Modules\Users\Models\Notification();
        $notifications = $notifModel->getActiveNotifications($userId);

        $this->render('settings', [
            'title' => 'Настройки профиля',
            'user' => $user,
            'notifications' => $notifications,
            'request' => new AppCoreRequest()
        ]);
    }

    /**
     * Обработка обновления настроек профиля (POST /account/settings).
     * Обновляет email, bio, аватар и настройки уведомлений.
     *
     * @return void
     */
    public function updateSettings(): void
    {
        $request = new AppCoreRequest();
        $request->validateCsrf();

        $userId = (int)$_SESSION['user_id'];
        $user = $this->getUserService()->getUserWithProfile($userId);

        if (!$user) {
            header('Location: /');
            exit;
        }

        $email = trim($request->getParams('email'));
        $bio = trim($request->getParams('bio'));
        $oldAvatarFilename = $user['avatar'];
        $newAvatarFilename = $oldAvatarFilename;

        if ($email !== $user['email']) {
            if (!$this->getUserService()->updateEmail($userId, $email)) {
                header('Location: ' . route('account.settings'));
                exit;
            }
        }

        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedAvatar = $this->getAvatarService()->handleUpload($_FILES['avatar_file'], $oldAvatarFilename);
            if ($uploadedAvatar) {
                $newAvatarFilename = $uploadedAvatar;
            }
        }

        $this->getUserService()->updateProfile($userId, [
            'bio' => $bio,
            'avatar' => $newAvatarFilename
        ]);

        $this->getUserService()->updateSettings($userId, [
            'notify_on_reply' => $request->getParams('notify_on_reply') ? 1 : 0,
            'notify_on_story_comment' => $request->getParams('notify_on_story_comment') ? 1 : 0,
            'email_notifications' => $request->getParams('email_notifications') ? 1 : 0,
        ]);

        $_SESSION['user_avatar'] = $newAvatarFilename;

        AppCoreSession::setFlash('success', 'Настройки сохранены.');
        header('Location: ' . route('account.settings'));
        exit;
    }

    /**
     * Обработка смены пароля (POST /account/password).
     * Проверяет текущий пароль и обновляет на новый.
     *
     * @return void
     */
    public function updatePassword(): void
    {
        $request = new AppCoreRequest();
        $request->validateCsrf();

        $userId = (int)$_SESSION['user_id'];
        $currentPassword = $request->getParams('current_password');
        $newPassword = $request->getParams('new_password');

        if (strlen($newPassword) < 6) {
            AppCoreSession::setFlash('error', 'Пароль должен быть не менее 6 символов.');
            header('Location: ' . route('account.settings'));
            exit;
        }

        if ($this->getUserService()->changePassword($userId, $currentPassword, $newPassword)) {
            AppCoreSession::setFlash('success', 'Пароль успешно изменён.');
        }

        header('Location: ' . route('account.settings'));
        exit;
    }

}