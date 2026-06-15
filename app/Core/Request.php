<?php

namespace App\Core;

class Request
{
    public function getUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        if ($position = strpos($uri, '?')) {
            $uri = substr($uri, 0, $position);
        }
        return $uri === '/' ? '/' : trim($uri, '/');
    }

    public function getMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function isPost(): bool
    {
        return $this->getMethod() === 'POST';
    }

    public function isGet(): bool
    {
        return $this->getMethod() === 'GET';
    }

    /**
     * БЕЗОПАСНО: Возвращает чистые, исходные данные без принудительного искажения.
     */
    public function getParams(?string $key = null, $default = null)
    {
        $data = [];

        if ($this->isGet()) {
            $data = $_GET;
        }

        if ($this->isPost()) {
            $data = $_POST;
        }

        if ($key !== null) {
            return $data[$key] ?? $default;
        }

        return $data;
    }

    public function getCsrfToken(): string
    {
        // Если сессия не запущена, принудительно стартуем её
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // КРИТИЧЕСКИЙ ФИКС: Генерируем токен ТОЛЬКО если его еще нет в сессии
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }


    /**
     * Вывод скрытого HTML-поля токена для вставки в формы
     */
    public function csrfField(): string
    {
        $token = $this->getCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    /**
     * Validates incoming form tokens against active session cryptographic payloads
     */
    public function validateCsrf(): void
    {
        // 1. Capture parameters securely and fall back to empty strings instead of null
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $submittedToken = $this->getParams('csrf_token') ?? '';

        // 2. Perform validation checks safely without breaking on PHP 8 strict types
        if (empty($sessionToken) || empty($submittedToken) || !hash_equals((string)$sessionToken, (string)$submittedToken)) {

            // Log security exploit footprint tokens into your audit database logs natively
            \App\Core\Audit::log('security.csrf_failed', 'Обнаружена атака CSRF / Неверный проверочный токен формы', [
                'ip'  => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'url' => $_SERVER['REQUEST_URI'] ?? '/'
            ]);

            // Clear out stale session parameters safely
            \App\Core\Session::setFlash('error', 'Срок действия сессии формы истек. Пожалуйста, попробуйте еще раз.');

            // Bounce the attacker or expired form user back to the referrer source
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
            exit;
        }
    }
	
	/**
	 * Проверяет CSRF-токен без редиректа (для AJAX-запросов)
	 * 
	 * @return bool true если токен валиден, false если нет
	 */
	public function isCsrfValid(): bool
	{
		$sessionToken = $_SESSION['csrf_token'] ?? '';
		$submittedToken = $this->getParams('csrf_token') ?? '';

		if (empty($sessionToken) || empty($submittedToken)) {
			return false;
		}

		return hash_equals((string)$sessionToken, (string)$submittedToken);
	}
}
