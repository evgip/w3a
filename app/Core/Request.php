<?php
// app/Core/Request.php

namespace App\Core;

class Request
{
    private const CSRF_TOKEN_KEY = 'csrf_token';
    private const CSRF_TOKEN_NAME = 'csrf_token';

    /**
     * Получить URI без query-параметров
     */
	public function getUri(): string
	{
		$uri = $_SERVER['REQUEST_URI'] ?? '/';
		
		if ($position = strpos($uri, '?')) {
			$uri = substr($uri, 0, $position);
		}
		
		$uri = rtrim($uri, '/');
		return $uri === '' ? '/' : (str_starts_with($uri, '/') ? $uri : '/' . $uri);
	}

    /**
     * Получить HTTP-метод
     */
    public function getMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Проверка POST-запроса
     */
    public function isPost(): bool
    {
        return $this->getMethod() === 'POST';
    }

    /**
     * Проверка GET-запроса
     */
    public function isGet(): bool
    {
        return $this->getMethod() === 'GET';
    }

    /**
     * Получить параметры запроса (GET или POST)
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

    /**
     * Получить или сгенерировать CSRF-токен
     */
    public function getCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION[self::CSRF_TOKEN_KEY])) {
            $_SESSION[self::CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION[self::CSRF_TOKEN_KEY];
    }

    /**
     * HTML-поле с токеном для вставки в формы
     */
    public function csrfField(): string
    {
        $token = htmlspecialchars($this->getCsrfToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="' . self::CSRF_TOKEN_NAME . '" value="' . $token . '">';
    }

    /**
     * Валидация CSRF-токена с обработкой ошибки (для обычных форм)
     */
    public function validateCsrf(): void
    {
        // GET-запросы не требуют CSRF
        if ($this->isGet()) {
            return;
        }

        $sessionToken = $_SESSION[self::CSRF_TOKEN_KEY] ?? '';
        $submittedToken = $this->getParams(self::CSRF_TOKEN_NAME) ?? '';

        // Timing-safe сравнение
        if (empty($sessionToken) || empty($submittedToken) || 
            !hash_equals((string)$sessionToken, (string)$submittedToken)) {
            
            $this->handleCsrfFailure();
        }
    }

    /**
     * Валидация CSRF без редиректа (для AJAX-запросов)
     */
    public function isCsrfValid(): bool
    {
        $sessionToken = $_SESSION[self::CSRF_TOKEN_KEY] ?? '';
        $submittedToken = $this->getParams(self::CSRF_TOKEN_NAME) ?? '';
        
        if (empty($sessionToken) || empty($submittedToken)) {
            return false;
        }
        
        return hash_equals((string)$sessionToken, (string)$submittedToken);
    }

    /**
     * Обработка провала CSRF-валидации
     */
    private function handleCsrfFailure(): void
    {
        // 1. Логируем попытку атаки через существующий Audit
        Audit::log('security.csrf_failed', 'Неверный CSRF-токен', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'url' => $_SERVER['REQUEST_URI'] ?? '/',
            'method' => $this->getMethod(),
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        // 2. Регенерируем токен (предотвращает повторное использование)
        unset($_SESSION[self::CSRF_TOKEN_KEY]);

        // 3. Flash-сообщение для пользователя
        Session::setFlash('error', 'Срок действия формы истёк. Пожалуйста, обновите страницу и попробуйте снова.');

        // 4. Для AJAX — возвращаем JSON
        if ($this->isAjaxRequest()) {
            http_response_code(419);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => 'CSRF token validation failed',
                'message' => 'Срок действия формы истёк. Обновите страницу.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 5. Для обычных запросов — используем существующий ErrorsController
        http_response_code(419);
        
        $errorController = new \App\Modules\Errors\Controllers\ErrorsController();
        $errorController->csrf('Срок действия формы истёк. Пожалуйста, обновите страницу и попробуйте снова.');
        exit;
    }

    /**
     * Проверка AJAX-запроса
     */
    private function isAjaxRequest(): bool
    {
        return (
            (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
                && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || 
            (!empty($_SERVER['HTTP_ACCEPT']) 
                && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
        );
    }
}