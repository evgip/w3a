<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Core\Controller;
use App\Core\Captcha;
use App\Core\Session as AppCoreSession;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\PasswordResetService;

/**
 * Контроллер для управления пользователями: аутентификация, регистрация, профиль и настройки.
 *
 * Все действия (кроме login/showLoginForm/showRegisterForm) требуют авторизации через сессию.
 */
class AuthController extends Controller
{
	/**
	 * Форма запроса ссылки для восстановления пароля (GET /password/reset).
	 */
	public function showRequestResetForm(): void
	{
		$this->render('password/reset_request', [
			'title' => 'Восстановление пароля'
		]);
	}

	/**
	 * Отправка ссылки для восстановления на email (POST /password/reset).
	 */
	public function sendResetLink(): void
	{
        // === ПРОВЕРКА КАПЧИ ===
        if (!Captcha::verify()) {
            Session::setFlash('error', 'Пожалуйста, подтвердите, что вы не робот.');
            $this->redirect(route('password.request'));
            return;
        }
		
		$email = trim($this->request->getParams('email'));

		if (empty($email)) {
			\App\Core\Session::setFlash('error', 'Введите email.');
			$this->redirect(route('password.request'));
			return;
		}

		// Отправляем ссылку (всегда возвращает true для защиты от enumeration)
		$this->getPasswordResetService()->sendResetLink($email);

		// Показываем сообщение даже если email не найден (безопасность)
		\App\Core\Session::setFlash('success', 'Если email зарегистрирован, ссылка для восстановления отправлена.');
		$this->redirect(route('password.request'));
	}

	/**
	 * Форма ввода нового пароля (GET /password/reset/{token}).
	 */
	public function showResetPasswordForm(string $token): void
	{
		// Проверяем валидность токена
		$user = $this->getPasswordResetService()->validateToken($token);

		if (!$user) {
			\App\Core\Session::setFlash('error', 'Ссылка недействительна или истекла.');
			$this->redirect(route('password.request'));
			return;
		}

		$this->render('password/reset_form', [
			'title' => 'Установить новый пароль',
			'token' => $token
		]);
	}

	/**
	 * Обработка смены пароля (POST /password/reset/submit).
	 */
	public function executePasswordReset(): void
	{
		$token = $this->request->getParams('token');
		$password = $this->request->getParams('password');
		$passwordConfirm = $this->request->getParams('password_confirm');

		// Валидация
		if (empty($token) || empty($password) || empty($passwordConfirm)) {
			\App\Core\Session::setFlash('error', 'Заполните все поля.');
			$this->redirect(route('password.reset', ['token' => $token]));
			return;
		}

		if (strlen($password) < 6) {
			\App\Core\Session::setFlash('error', 'Пароль должен быть не менее 6 символов.');
			$this->redirect(route('password.reset', ['token' => $token]));
			return;
		}

		if ($password !== $passwordConfirm) {
			\App\Core\Session::setFlash('error', 'Пароли не совпадают.');
			$this->redirect(route('password.reset', ['token' => $token]));
			return;
		}

		// Сбрасываем пароль
		$success = $this->getPasswordResetService()->resetPassword($token, $password);

		if ($success) {
			\App\Core\Session::setFlash('success', 'Пароль успешно изменён. Теперь вы можете войти.');
			$this->redirect(route('auth.login'));
		} else {
			\App\Core\Session::setFlash('error', 'Ошибка при смене пароля. Попробуйте запросить новую ссылку.');
			$this->redirect(route('password.request'));
		}
	}

	/**
	 * Получить PasswordResetService из контейнера.
	 */
	private function getPasswordResetService(): \App\Modules\Auth\Services\PasswordResetService
	{
		return $this->service(\App\Modules\Auth\Services\PasswordResetService::class);
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

		$user = $this->service(AuthService::class)->authenticate($email, $password);
		
		if (!$user) {
			// При неудаче — редирект на форму логина
			$this->redirectWithError('/login', 'Неверный email или пароль');
			return;
		}

		// Передаем параметр $remember в createSession
		$this->service(AuthService::class)->createSession($user, $remember);
		
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
        // === ПРОВЕРКА КАПЧИ ===
        if (!Captcha::verify()) {
            Session::setFlash('error', 'Пожалуйста, подтвердите, что вы не робот.');
            $this->redirect(route('auth.register'));
            return;
        }
		
		
        $username = trim($this->request->getParams('username'));
        $email = trim($this->request->getParams('email'));
        $password = $this->request->getParams('password');

        $userId = $this->service(AuthService::class)->register($username, $email, $password);
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
        $this->service(AuthService::class)->logout();
        header('Location: /');
        exit;
    }

	/**
	 * Активация аккаунта по токену из email (GET /register/activate/{token}).
	 */
	public function activateAccount(string $token): void
	{
		$success = $this->getAuthService()->activateAccount($token);

		if ($success) {
			\App\Core\Session::setFlash('success', 'Аккаунт успешно активирован! Теперь вы можете войти.');
			$this->redirect(route('auth.login'));
		} else {
			\App\Core\Session::setFlash('error', 'Недействительная или устаревшая ссылка активации.');
			$this->redirect(route('auth.register'));
		}
	}
	
	/**
	 * Получить AuthService из контейнера.
	 * 
	 * @return \App\Modules\Auth\Services\AuthService
	 */
	private function getAuthService(): \App\Modules\Auth\Services\AuthService
	{
		return $this->service(\App\Modules\Auth\Services\AuthService::class);
	}

}