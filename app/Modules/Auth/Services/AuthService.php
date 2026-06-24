<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Modules\Users\Models\User;
use App\Modules\Auth\Models\RememberToken;
use App\Modules\Auth\Models\EmailActivation;
use App\Core\Session as AppCoreSession;
use App\Core\Database;
use App\Core\Audit;
use App\Core\Config;
use App\Core\Mailer;

class AuthService
{
    private User $userModel;
	private RememberToken $rememberTokenModel;
	private EmailActivation $emailActivationModel;

    // Максимальное количество неудачных попыток входа до блокировки
    private const MAX_ATTEMPTS_IP = 5;        // с одного IP-адреса
    private const MAX_ATTEMPTS_EMAIL = 10;    // для одного email-адреса
    private const LOCKOUT_MINUTES = 15;       // длительность блокировки в минутах
    // Константный «dummy» хэш для защиты от timing-атак при несуществующем email
    private const DUMMY_HASH = '$2y$10$DummyHashForTimingProtection00000000000000000000';

    private const COOKIE_NAME = 'remember_me';
    private const COOKIE_DAYS = 30;

    /**
     * @param User|null $userModel Модель пользователя
     * @param RememberToken|null $rememberTokenModel Модель токенов "Запомнить меня"
     */
    public function __construct(
        ?User $userModel = null,
        ?RememberToken $rememberTokenModel = null,
        ?EmailActivation $emailActivationModel = null
    ) {
        $this->userModel = $userModel ?? new User();
        $this->rememberTokenModel = $rememberTokenModel ?? new RememberToken();
        $this->emailActivationModel = $emailActivationModel ?? new EmailActivation();
    }

    /**
     * Выполняет аутентификацию пользователя с защитой от brute-force и timing-атак.
     *
     * @param string $email    Электронная почта
     * @param string $password Пароль в открытом виде
     * @return array|null     Данные пользователя при успехе, иначе null
     */
    public function authenticate(string $email, string $password): ?array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        // 1. Проверяем, не заблокирован ли IP или email (без обращения к таблице users)
        $blockType = $this->checkBlockStatus($ip, $email);
        if ($blockType !== null) {
            $this->showBlockMessage($blockType);
            return null;
        }
        
        // 2. Ищем пользователя по email
        $user = $this->userModel->findBy('email', $email);
        
        // 3. Проверяем пароль с защитой от timing-атак
        if (!$user) {
            // Если email не найден — всё равно выполняем hash_verify с dummy-хэшем
            // для стабилизации времени ответа (защита от атак по времени)
            password_verify($password, self::DUMMY_HASH);
            $this->logFailedAttempt($ip, $email);
            return null;
        }
        
        if (!password_verify($password, $user['password'])) {
            $this->logFailedAttempt($ip, $email);
            return null;
        }
        
        // 4. Проверка активности аккаунта
        if ((int)$user['is_active'] !== 1) {
            AppCoreSession::setFlash('error', 'Аккаунт не активирован.');
            $this->logFailedAttempt($ip, $email, 'inactive_account');
            return null;
        }
        
        // 5. При успешной аутентификации очищаем историю неудачных попыток
        $this->clearFailedAttempts($ip, $email);
        
        return $user;
    }

    /**
     * Проверяет текущую блокировку по IP или email.
     *
     * @param string $ip    IP-адрес
     * @param string $email Email (может быть пустой строкой)
     * @return string|null  'ip', 'email', или null — если блокировки нет
     */
    private function checkBlockStatus(string $ip, string $email): ?string
    {
        if ($this->isIpBlocked($ip)) {
            return 'ip';
        }
        if ($email !== '' && $this->isEmailBlocked($email)) {
            return 'email';
        }
        return null;
    }

    /**
     * Отображает пользователю сообщение о временной блокировке.
     *
     * @param string $type Тип блокировки: 'ip' или 'email'
     */
    private function showBlockMessage(string $type): void
    {
        $msg = match ($type) {
            'ip' => 'Слишком много попыток с вашего IP. Подождите ' . self::LOCKOUT_MINUTES . ' минут.',
            'email' => 'Слишком много попыток входа для этого аккаунта. Подождите ' . self::LOCKOUT_MINUTES . ' минут.',
            default => 'Вход временно недоступен.',
        };
        AppCoreSession::setFlash('error', $msg);
    }

    /**
     * Проверяет, заблокирован ли IP-адрес (по количеству неудачных попыток).
     *
     * @param string $ip IP-адрес
     * @return bool true, если IP заблокирован, иначе false
     */
    private function isIpBlocked(string $ip): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM audit_logs 
            WHERE action = 'auth.login_failed' 
            AND ip_address = ?
            AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$ip, self::LOCKOUT_MINUTES]);
        return (int)$stmt->fetchColumn() >= self::MAX_ATTEMPTS_IP;
    }

    /**
     * Проверяет, заблокирован ли email (по количеству неудачных попыток).
     *
     * @param string $email Email-адрес
     * @return bool true, если email заблокирован, иначе false
     */
    private function isEmailBlocked(string $email): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM audit_logs 
            WHERE action = 'auth.login_failed' 
            AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.email')) = ?
            AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$email, self::LOCKOUT_MINUTES]);
        return (int)$stmt->fetchColumn() >= self::MAX_ATTEMPTS_EMAIL;
    }

    /**
     * Логирует неудачную попытку входа в audit_logs.
     *
     * @param string $ip       IP-адрес
     * @param string $email    Email-адрес (даже если пустой)
     * @param string $reason   Причина (например, 'invalid_credentials' или 'inactive_account')
     */
    private function logFailedAttempt(string $ip, string $email, string $reason = 'invalid_credentials'): void
    {
        Audit::log(
            'auth.login_failed',
            "Неудачная попытка входа",
            'auth',  // категория события
            [
                'email' => $email,
                'ip' => $ip,
                'reason' => $reason,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'timestamp' => date('c')
            ]
        );
    }

    /**
     * Удаляет все записи о неудачных попытках для данного IP и/или email.
     *
     * @param string $ip    IP-адрес
     * @param string $email Email-адрес
     */
    private function clearFailedAttempts(string $ip, string $email): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            DELETE FROM audit_logs 
            WHERE action = 'auth.login_failed' 
            AND (
                ip_address = ? 
                OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.email')) = ?
            )
        ");
        $stmt->execute([$ip, $email]);
    }

    /**
     * Возвращает оставшееся время блокировки по IP (в секундах).
     *
     * @param string $ip IP-адрес
     * @return int Количество секунд до снятия блокировки (0 — если блокировка окончена)
     */
    public function getRemainingLockoutTime(string $ip): int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT created_at 
            FROM audit_logs 
            WHERE action = 'auth.login_failed' 
            AND ip_address = ?
            AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$ip, self::LOCKOUT_MINUTES]);
        $lastAttempt = $stmt->fetchColumn();
        
        if (!$lastAttempt) return 0;
        
        $lockoutUntil = strtotime($lastAttempt) + (self::LOCKOUT_MINUTES * 60);
        return max(0, $lockoutUntil - time());
    }

    /**
     * Регистрирует нового пользователя и отправляет письмо активации.
     *
     * @param string $username Имя пользователя
     * @param string $email    Электронная почта
     * @param string $password Пароль в открытом виде
     * @return int|null ID зарегистрированного пользователя или null при ошибке
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

        // Создаём пользователя с is_active = 0 (неактивен до подтверждения email)
        $userId = $this->userModel->create([
            'username' => $username,
            'email' => $email,
            'password' => $hashedPassword,
            'role' => 'user',
            'is_active' => 0  // ⚠️ Неактивен до активации
        ]);

        if ($userId > 0) {
            // Инициализация профиля и пользовательских настроек
            $this->userModel->updateProfile($userId, ['bio' => null, 'avatar' => null]);
            $this->userModel->updateSettings($userId, [
                'notify_on_reply' => 1,
                'notify_on_story_comment' => 1,
                'email_notifications' => 0
            ]);

            // === ОТПРАВКА ПИСЬМА АКТИВАЦИИ ===
            $activationToken = bin2hex(random_bytes(32));
            
            // Сохраняем токен в таблице email_activations
            $this->emailActivationModel->createToken($userId, $activationToken);

            // Отправляем письмо
            $this->sendActivationEmail($email, $username, $activationToken);

            Audit::log('auth.register', "Зарегистрирован новый пользователь", 'auth', [
                'user_id' => $userId,
                'email' => $email,
                'username' => $username
            ]);
        }

        return $userId > 0 ? $userId : null;
    }

	/**
	 * Активировать аккаунт по токену из email.
	 *
	 * @param string $token Токен активации из ссылки
	 * @return bool Успешность активации
	 */
	public function activateAccount(string $token): bool
	{
		if (empty($token)) {
			return false;
		}

		// Ищем токен в таблице email_activations
		$tokenData = $this->emailActivationModel->findByToken($token);

		if (!$tokenData) {
			return false;
		}

		// Проверяем срок действия (24 часа от created_at)
		$createdAt = strtotime($tokenData['created_at']);
		if ((time() - $createdAt) > 86400) { // 24 часа = 86400 секунд
			// Токен просрочен — удаляем
			$this->emailActivationModel->deleteByToken($token);
			return false;
		}

		// Получаем данные пользователя
		$user = $this->userModel->find((int)$tokenData['user_id']);

		if (!$user) {
			$this->emailActivationModel->deleteByToken($token);
			return false;
		}

		// Активируем аккаунт
		$success = $this->userModel->update((int)$user['id'], [
			'is_active' => 1
		]);

		if ($success) {
			// Удаляем токен (он одноразовый)
			$this->emailActivationModel->deleteByToken($token);

			Audit::log('auth.account_activated', "Аккаунт активирован", 'auth', [
				'user_id' => $user['id'],
				'email' => $user['email']
			]);
		}

		return $success;
	}

    /**
     * Отправить письмо активации аккаунта.
     */
    private function sendActivationEmail(string $email, string $username, string $token): void
    {
        // Получаем базовый URL
        $baseUrl = Config::get('config.app.url');
        if (empty($baseUrl)) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $scheme . '://' . $host;
        }

        $activationUrl = rtrim($baseUrl, '/') . '/register/activate/' . $token;
        $siteName = Config::get('config.app.name') ?? $baseUrl;

        // Формируем тему и тело через языковые ключи
        $subject = sprintf(
            \App\Core\Lang::get('email_activation_subject'),
            htmlspecialchars($siteName)
        );

        $body = sprintf(
            \App\Core\Lang::get('email_activation_body'),
            htmlspecialchars($username),
            htmlspecialchars($activationUrl)
        );

		Audit::log('auth.activation_email', "Отправка письма активации", 'auth', [
			'to' => $email,
			'subject' => $subject,
		]);

        $result = Mailer::send($email, $subject, $body);

        if (!$result) {
            Audit::log('auth.activation_email_failed', "Не удалось отправить письмо активации", 'auth', [
				'email' => $email
			]);
        }
    }

    /**
     * Создаёт сессию для аутентифицированного пользователя.
     *
     * @param array $user Данные пользователя (должны содержать id, username, role и др.)
     */
    public function createSession(array $user, bool $remember = false): void
    {
        // Регенерируем ID сессии для безопасности
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['username'] ?? $user['name'];
        $_SESSION['user_role'] = $user['role'] ?? 'user';
        $_SESSION['last_activity_time'] = time();

        // Получаем аватар из профиля
        $profile = $this->userModel->getProfile((int)$user['id']);
        $_SESSION['user_avatar'] = $profile['avatar'] ?? null;

        // Если отмечен чекбокс "Запомнить меня" - создаем токен
        if ($remember) {
            $this->createRememberCookie($user['id']);
        }

        // Аудит
        Audit::log('auth.login_success', "Пользователь вошел в систему", 'auth');
    }

   /**
     * Создать cookie "Запомнить меня"
     */
	private function createRememberCookie(int $userId): void
	{
		$tokenData = $this->rememberTokenModel->createToken(
			$userId,
			self::COOKIE_DAYS,
			$_SERVER['HTTP_USER_AGENT'] ?? null,
			$_SERVER['REMOTE_ADDR'] ?? null
		);

        // Устанавливаем cookie на 30 дней
        $expiry = time() + (self::COOKIE_DAYS * 86400);
        
        setcookie(
            self::COOKIE_NAME,
            $tokenData['token'],
            [
                'expires' => $expiry,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
    }

    /**
     * Проверить cookie "Запомнить меня" и восстановить сессию
     * Вызывается при каждом запросе, если сессия пуста
     *
     * @return bool true если сессия восстановлена
     */
	public function attemptRememberLogin(): bool
	{
		if (!isset($_COOKIE[self::COOKIE_NAME])) {
			return false;
		}

		$token = $_COOKIE[self::COOKIE_NAME];
		
		// Разделяем токен на selector и validator
		$parts = explode(':', $token, 2);
		if (count($parts) !== 2) {
			$this->clearRememberCookie();
			return false;
		}

		[$selector, $validator] = $parts;

		$record = $this->rememberTokenModel->validateToken($selector, $validator);

		if (!$record) {
			// Токен невалиден или истек - удаляем cookie
			$this->clearRememberCookie();
			return false;
		}

		// Получаем данные пользователя
		$user = $this->userModel->find((int)$record['user_id']);

        if (!$user) {
            $this->clearRememberCookie();
            return false;
        }

        // Проверяем, не забанен ли пользователь
        if ($this->userModel->isBanned((int)$user['id'])) {
            $this->clearRememberCookie();
            Audit::log('auth.remember_blocked', "Попытка входа по токену забаненного пользователя", 'auth');
			
            return false;
        }

        // Восстанавливаем сессию
        $this->createSession($user, false); // false - не создавать новый remember токен

        // Обновляем токен (rotation) для безопасности
        $this->createRememberCookie($user['id']);

        Audit::log('auth.remember_success', "Восстановление сессии по токену", 'auth');

        return true;
    }

    /**
     * Очистить cookie "Запомнить меня"
     */
	private function clearRememberCookie(): void
	{
		if (isset($_COOKIE[self::COOKIE_NAME])) {
			// Удаляем токен из БД
			$token = $_COOKIE[self::COOKIE_NAME];
			$parts = explode(':', $token, 2);
			if (count($parts) === 2) {
				$this->rememberTokenModel->deleteBySelector($parts[0]);
			}

            // Удаляем cookie
            setcookie(
                self::COOKIE_NAME,
                '',
                [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'domain' => '',
                    'secure' => isset($_SERVER['HTTPS']),
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
            unset($_COOKIE[self::COOKIE_NAME]);
        }
    }


    /**
     * Завершает текущую сессию пользователя.
     */
    public function logout(): void
    {
        // Удаляем remember токен
        $this->clearRememberCookie();

        // Если есть user_id - логируем выход
        if (isset($_SESSION['user_id'])) {
            Audit::log('auth.logout', "Пользователь вышел из системы", 'auth');
        }

        // Уничтожаем сессию
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        session_destroy();
    }
}