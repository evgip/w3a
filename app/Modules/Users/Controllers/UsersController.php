<?php

declare(strict_types=1);

namespace App\Modules\Users\Controllers;

use App\Core\Controller;
use App\Core\Session as AppCoreSession;
use App\Modules\Users\Services\UserService;
use App\Modules\Users\Services\AuthService;
use App\Modules\Users\Services\AvatarService;

/**
 * Контроллер для управления пользователями: аутентификация, регистрация, профиль и настройки.
 *
 * Все действия (кроме login/showLoginForm/showRegisterForm) требуют авторизации через сессию.
 */
class UsersController extends Controller
{
    private ?UserService $userService = null;
    private ?AuthService $authService = null;
    private ?AvatarService $avatarService = null;

    /**
     * Получить экземпляр сервиса работы с пользователями (ленивая инициализация).
     *
     * @return UserService Экземпляр UserService
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
     * @return AuthService Экземпляр AuthService
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
     * @return AvatarService Экземпляр AvatarService
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
     * Валидирует CSRF-токен (через `Controller::validateCsrfToken()`), проверяет email/пароль, создаёт сессию.
     * При успехе перенаправляет на главную, при ошибке — возвращается на /login без перезагрузки (но здесь — редирект).
     *
     * @return void
     */
	public function login(): void
	{
		$email = trim($this->request->getParams('email'));
		$password = $this->request->getParams('password');
		
		// Читаем параметр "Запомнить меня"
		$remember = (bool)$this->request->getParams('remember');

		$user = $this->getAuthService()->authenticate($email, $password);
		
		if (!$user) {
			// При неудаче — редирект на форму логина
			$this->redirectWithError('/login', 'Неверный email или пароль');
			return;
		}

		// Передаем параметр $remember в createSession
		$this->getAuthService()->createSession($user, $remember);
		
		AppCoreSession::setFlash('success', 'Добро пожаловать!');
		$this->redirect('/');
	}

    /**
     * Отображение формы логина (GET /login).
     *
     * @return void
     */
    public function showLoginForm(): void
    {
        // Рендерим шаблон login.php из папки Views модуля Users
        $this->render('login', [
            'title' => 'Авторизация',
            'request' => $this->request // Передаем объект запроса для вывода CSRF-поля
        ]);
    }

    /**
     * Отображение формы регистрации (GET /register).
     * Загружает ранее введённые данные (если были ошибки валидации), чтобы не просить пользователя вводить их заново.
     *
     * @return void
     */
    public function showRegisterForm(): void
    {
        // Получаем старые значения из сессии (если есть), удаляем после использования
        $old = \App\Core\Session::get('old_input', []);
        \App\Core\Session::delete('old_input');

        $this->render('register', [
            'title' => 'Регистрация нового пользователя',
            'request' => $this->request,
            'old' => $old
        ]);
    }

    /**
     * Обработка регистрации нового пользователя (POST /register).
     * Валидирует CSRF-токен, создаёт пользователя, инициализирует профиль и настройки по умолчанию.
     *
     * При ошибке (email/username уже заняты) — редирект на форму регистрации с сохранением ввода.
     * При успехе — редирект на /login с сообщением о подтверждении через почту.
     *
     * @return void
     */
    public function register(): void
    {
        $username = trim($this->request->getParams('username'));
        $email = trim($this->request->getParams('email'));
        $password = $this->request->getParams('password');

        $userId = $this->getAuthService()->register($username, $email, $password);
        if (!$userId) {
            // Сохраняем ввод в сессию для повторной отрисовки формы
            \App\Core\Session::set('old_input', [
                'username' => $username,
                'email' => $email,
            ]);
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
     * Загружает данные пользователя, профиль, информацию о бане, статистику (stories/comments) и карму.
     *
     * @param string $username Имя пользователя (username) — обязательный параметр маршрута
     * @return void
     */
    public function profile(string $username): void
    {
        $userModel = new \App\Modules\Users\Models\User();
        $user = $userModel->findBy('username', trim($username));

        if (!$user) {
            // Альтернативный способ обработки 404 (если контроллер ошибок доступен)
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
            'storiesCount' => $stats['stories_count'] ?? 0,
            'commentsCount' => $stats['comments_count'] ?? 0,
            'userKarma' => $userKarma ?? 0
        ]);
    }

    /**
     * Отображение страницы настроек профиля (GET /account/settings).
     * Требует авторизации (проверяется через наличие `$_SESSION['user_id']` в `UserService::getUserWithProfile()`).
     * Загружает данные пользователя, профиля, настроек и активные уведомления.
     *
     * @return void
     */
    public function settings(): void
    {
        $userId = (int)$_SESSION['user_id'];
        $user = $this->getUserService()->getUserWithProfile($userId);

        if (!$user) {
            // Пользователь не авторизован или не найден — перенаправляем на главную
            header('Location: /');
            exit;
        }

        $notifModel = new \App\Modules\Users\Models\Notification();
        $notifications = $notifModel->getActiveNotifications($userId);

        $this->render('settings', [
            'title' => 'Настройки профиля',
            'user' => $user,
            'notifications' => $notifications,
            'request' => $this->request
        ]);
    }

    /**
     * Обработка обновления настроек профиля (POST /account/settings).
     * Обновляет email (с проверкой уникальности), bio, аватар и настройки уведомлений.
     *
     * При ошибке email (неуникальный) — редирект на форму с сохранением текущих данных.
     * При загрузке аватара — старый файл удаляется (логика в AvatarService).
     *
     * @return void
     */
    public function updateSettings(): void
    {
        $userId = (int)$_SESSION['user_id'];
        $user = $this->getUserService()->getUserWithProfile($userId);

        if (!$user) {
            header('Location: /');
            exit;
        }

        $email = trim($this->request->getParams('email'));
        $bio = trim($this->request->getParams('bio'));
        $oldAvatarFilename = $user['avatar'];
        $newAvatarFilename = $oldAvatarFilename;

        // Обновление email — проверка уникальности в UserService
        if ($email !== $user['email']) {
            if (!$this->getUserService()->updateEmail($userId, $email)) {
                header('Location: ' . route('account.settings'));
                exit;
            }
        }

        // Обработка загрузки аватара (если файл передан и без ошибок)
        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedAvatar = $this->getAvatarService()->handleUpload($_FILES['avatar_file'], $oldAvatarFilename);
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
            'email_notifications' => $this->request->getParams('email_notifications') ? 1 : 0,
        ]);

        $_SESSION['user_avatar'] = $newAvatarFilename;

        AppCoreSession::setFlash('success', 'Настройки сохранены.');
        header('Location: ' . route('account.settings'));
        exit;
    }

    /**
     * Обработка смены пароля (POST /account/password).
     * Проверяет текущий пароль, валидирует новую длину (минимум 6 символов), обновляет хэш в БД.
     *
     * Возвращает flash-сообщение (успех/ошибка), но не прерывает выполнение при ошибке.
     *
     * @return void
     */
    public function updatePassword(): void
    {
        $userId = (int)$_SESSION['user_id'];
        $currentPassword = $this->request->getParams('current_password');
        $newPassword = $this->request->getParams('new_password');

        if (strlen($newPassword) < 6) {
            AppCoreSession::setFlash('error', 'Пароль должен быть не менее 6 символов.');
            header('Location: ' . route('account.settings'));
            exit;
        }

        if ($this->getUserService()->changePassword($userId, $currentPassword, $newPassword)) {
            AppCoreSession::setFlash('success', 'Пароль успешно изменён.');
        } else {
            AppCoreSession::setFlash('error', 'Текущий пароль введён неверно.');
        }

        header('Location: ' . route('account.settings'));
        exit;
    }
}