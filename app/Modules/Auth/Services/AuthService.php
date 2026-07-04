<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Core\Session;
use App\Core\Validator;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Audit;
use App\Core\Config;
use App\Modules\Users\Models\User;
use App\Modules\Auth\Models\RememberToken;
use App\Modules\Auth\Models\EmailActivation;
use App\Modules\Captcha\Core\Captcha;

class AuthService
{
    private User $userModel;
    private RememberToken $rememberTokenModel;
    private EmailActivation $emailActivationModel;
    private Database $db;
    private Logger $logger;
    private Session $session;
    private Audit $audit;

    private const MAX_ATTEMPTS_IP = 5;
    private const MAX_ATTEMPTS_EMAIL = 10;
    private const LOCKOUT_MINUTES = 15;
    private const DUMMY_HASH = '$2y$10$DummyHashForTimingProtection00000000000000000000';

    private const COOKIE_NAME = 'remember_me';
    private const COOKIE_DAYS = 30;

    public function __construct(
        User $userModel,
        RememberToken $rememberTokenModel,
        EmailActivation $emailActivationModel,
        Database $db,
        Logger $logger,
        Session $session,
        Audit $audit,
		Mailer $mailer
    ) {
        $this->userModel = $userModel;
        $this->rememberTokenModel = $rememberTokenModel;
        $this->emailActivationModel = $emailActivationModel;
        $this->db = $db;
        $this->logger = $logger;
        $this->session = $session;
        $this->audit = $audit;
		 $this->mailer = $mailer;
    }

    public function authenticate(string $email, string $password): ?array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $blockType = $this->checkBlockStatus($ip, $email);
        if ($blockType !== null) {
            $this->showBlockMessage($blockType);
            return null;
        }

        $user = $this->userModel->findBy('email', $email);

        if (!$user) {
            password_verify($password, self::DUMMY_HASH);
            $this->logFailedAttempt($ip, $email);
            return null;
        }

        if (!password_verify($password, $user['password'])) {
            $this->logFailedAttempt($ip, $email);
            return null;
        }

        if ((int)$user['is_active'] !== 1) {
            $this->session->flash('error', 'Аккаунт не активирован.');
            $this->logFailedAttempt($ip, $email, 'inactive_account');
            return null;
        }

        $this->clearFailedAttempts($ip, $email);

        return $user;
    }

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

    private function showBlockMessage(string $type): void
    {
        $msg = match ($type) {
            'ip' => 'Слишком много попыток с вашего IP. Подождите ' . self::LOCKOUT_MINUTES . ' минут.',
            'email' => 'Слишком много попыток входа для этого аккаунта. Подождите ' . self::LOCKOUT_MINUTES . ' минут.',
            default => 'Вход временно недоступен.',
        };
        $this->session->flash('error', $msg);
    }

    private function isIpBlocked(string $ip): bool
    {
        $count = (int)$this->db->fetchColumn("
            SELECT COUNT(*) 
            FROM audit_logs 
            WHERE action = 'auth.login_failed' 
            AND ip_address = ?
            AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ", [$ip, self::LOCKOUT_MINUTES]);
        
        return $count >= self::MAX_ATTEMPTS_IP;
    }

    private function isEmailBlocked(string $email): bool
    {
        $count = (int)$this->db->fetchColumn("
            SELECT COUNT(*) 
            FROM audit_logs 
            WHERE action = 'auth.login_failed' 
            AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.email')) = ?
            AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ", [$email, self::LOCKOUT_MINUTES]);
        
        return $count >= self::MAX_ATTEMPTS_EMAIL;
    }

    private function logFailedAttempt(string $ip, string $email, string $reason = 'invalid_credentials'): void
    {
        // ✅ Используем $this->audit вместо Audit::log()
        $this->audit->log(
            'auth.login_failed',
            "Неудачная попытка входа",
            'auth',
            [
                'email' => $email,
                'ip' => $ip,
                'reason' => $reason,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'timestamp' => date('c')
            ]
        );
    }

    private function clearFailedAttempts(string $ip, string $email): void
    {
        $this->db->execute("
            DELETE FROM audit_logs 
            WHERE action = 'auth.login_failed' 
            AND (
                ip_address = ? 
                OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.email')) = ?
            )
        ", [$ip, $email]);
    }

    public function getRemainingLockoutTime(string $ip): int
    {
        $lastAttempt = $this->db->fetchColumn("
            SELECT created_at 
            FROM audit_logs 
            WHERE action = 'auth.login_failed' 
            AND ip_address = ?
            AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ORDER BY created_at DESC
            LIMIT 1
        ", [$ip, self::LOCKOUT_MINUTES]);

        if (!$lastAttempt) return 0;

        $lockoutUntil = strtotime($lastAttempt) + (self::LOCKOUT_MINUTES * 60);
        return max(0, $lockoutUntil - time());
    }

    public function register(string $username, string $email, string $password): ?int
    {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $userId = $this->userModel->create([
                'username' => $username,
                'email' => $email,
                'password' => $hashedPassword,
                'is_active' => 0,
                'role' => 'user',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            if (!$userId) {
                throw new \Exception('Не удалось создать пользователя');
            }

            $token = bin2hex(random_bytes(32));
            $this->emailActivationModel->createToken($userId, $token);
            $this->sendActivationEmail($email, $username, $token);

            return $userId;
        } catch (\Exception $e) {
            // ✅ Используем $this->audit
            $this->audit->log('auth.register_failed', "Ошибка регистрации: " . $e->getMessage(), 'auth', [
                'email' => $email,
                'username' => $username,
            ]);

            return null;
        }
    }

    public function activateAccount(string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        $tokenData = $this->emailActivationModel->findByToken($token);

        if (!$tokenData) {
            return false;
        }

        $createdAt = strtotime($tokenData['created_at']);
        if ((time() - $createdAt) > 86400) {
            $this->emailActivationModel->deleteByToken($token);
            return false;
        }

        $user = $this->userModel->find((int)$tokenData['user_id']);

        if (!$user) {
            $this->emailActivationModel->deleteByToken($token);
            return false;
        }

        $success = $this->userModel->update((int)$user['id'], [
            'is_active' => 1
        ]);

        if ($success) {
            $this->emailActivationModel->deleteByToken($token);

            // ✅ Используем $this->audit
            $this->audit->log('auth.account_activated', "Аккаунт активирован", 'auth', [
                'user_id' => $user['id'],
                'email' => $user['email']
            ]);
        }

        return $success;
    }

    private function sendActivationEmail(string $email, string $username, string $token): void
    {
        $baseUrl = Config::get('config.app.url');
        if (empty($baseUrl)) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $scheme . '://' . $host;
        }

        $activationUrl = rtrim($baseUrl, '/') . '/register/activate/' . $token;
        $siteName = Config::get('config.app.name') ?? $baseUrl;

        $subject = sprintf(
            \App\Core\Lang::get('email_activation_subject'),
            htmlspecialchars($siteName)
        );

        $body = sprintf(
            \App\Core\Lang::get('email_activation_body'),
            htmlspecialchars($username),
            htmlspecialchars($activationUrl)
        );

        // ✅ Используем $this->audit
        $this->audit->log('auth.activation_email', "Отправка письма активации", 'auth', [
            'to' => $email,
            'subject' => $subject,
        ]);

        $result = $this->mailer->send($email, $subject, $body);

        if (!$result) {
            $this->audit->log('auth.activation_email_failed', "Не удалось отправить письмо активации", 'auth', [
                'email' => $email
            ]);
        }
    }

    public function createSession(array $user, bool $remember = false): void
    {
        $this->session->regenerate(true);

        $this->session->set('user_id', $user['id']);
        $this->session->set('user_name', $user['username'] ?? $user['name']);
        $this->session->set('user_role', $user['role'] ?? 'user');
        $this->session->set('last_activity_time', time());

        $profile = $this->userModel->getProfile((int)$user['id']);
        $this->session->set('user_avatar', $profile['avatar'] ?? null);

        if ($remember) {
            $this->createRememberCookie($user['id']);
        }

        // ✅ Используем $this->audit
        $this->audit->log('auth.login_success', "Пользователь вошел в систему", 'auth');
    }

    private function createRememberCookie(int $userId): void
    {
        $tokenData = $this->rememberTokenModel->createToken(
            $userId,
            self::COOKIE_DAYS,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null
        );

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

    public function attemptRememberLogin(): bool
    {
        if (!isset($_COOKIE[self::COOKIE_NAME])) {
            return false;
        }

        $token = $_COOKIE[self::COOKIE_NAME];

        $parts = explode(':', $token, 2);
        if (count($parts) !== 2) {
            $this->clearRememberCookie();
            return false;
        }

        [$selector, $validator] = $parts;

        $record = $this->rememberTokenModel->validateToken($selector, $validator);

        if (!$record) {
            $this->clearRememberCookie();
            return false;
        }

        $user = $this->userModel->find((int)$record['user_id']);

        if (!$user) {
            $this->clearRememberCookie();
            return false;
        }

        if ($this->userModel->isBanned((int)$user['id'])) {
            $this->clearRememberCookie();
            $this->audit->log('auth.remember_blocked', "Попытка входа по токену забаненного пользователя", 'auth');
            return false;
        }

        $this->createSession($user, false);
        $this->createRememberCookie($user['id']);

        $this->audit->log('auth.remember_success', "Восстановление сессии по токену", 'auth');

        return true;
    }

    private function clearRememberCookie(): void
    {
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            $token = $_COOKIE[self::COOKIE_NAME];
            $parts = explode(':', $token, 2);
            if (count($parts) === 2) {
                $this->rememberTokenModel->deleteBySelector($parts[0]);
            }

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

    public function logout(): void
    {
        $this->clearRememberCookie();

        if ($this->session->has('user_id')) {
            try {
                $this->audit->log('auth.logout', "Пользователь вышел из системы", 'auth');
            } catch (\Throwable $e) {
                // Игнорируем ошибки логирования при выходе
            }
        }

        // Сохраняем flash-сообщения
        $flashData = $_SESSION['flash'] ?? null;

        // Очищаем сессию
        $this->session->clear();
        $this->session->destroy();

        // Стартуем новую сессию для flash
        session_start();
        
        if ($flashData) {
            $_SESSION['flash'] = $flashData;
        }
    }
}