<?php

namespace App\Core;

/**
 * Класс для работы с HTTP-запросом.
 * 
 * ✅ ИЗМЕНЕНО: Audit, Session и Container внедряются через конструктор.
 */
class Request
{
    private const CSRF_TOKEN_KEY = 'csrf_token';
    private const CSRF_TOKEN_NAME = 'csrf_token';

    /** @var Audit|null Сервис аудита (внедряется через setAudit() или конструктор) */
    private ?Audit $audit = null;

    /** @var Session|null Сервис сессий (внедряется через setSession() или конструктор) */
    private ?Session $session = null;

    /** @var Container|null DI-контейнер (внедряется через setContainer() или конструктор) */
    private ?Container $container = null;

    /**
     * ✅ Конструктор с опциональными зависимостями
     * 
     * Зависимости можно передать позже через сеттеры, если Request
     * создаётся до инициализации контейнера.
     */
    public function __construct(
        ?Audit $audit = null,
        ?Session $session = null,
        ?Container $container = null
    ) {
        $this->audit = $audit;
        $this->session = $session;
        $this->container = $container;
    }

    /**
     * ✅ Сеттер для Audit (если не передан в конструктор)
     */
    public function setAudit(Audit $audit): void
    {
        $this->audit = $audit;
    }

    /**
     * ✅ Сеттер для Session (если не передан в конструктор)
     */
    public function setSession(Session $session): void
    {
        $this->session = $session;
    }

    /**
     * ✅ Сеттер для Container (если не передан в конструктор)
     */
    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

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
	public function getParams(?string $key = null, mixed $default = null): mixed
	{
		$data = $_GET;
		
		if (in_array($this->getMethod(), ['POST', 'PUT', 'PATCH'])) {
			$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
			
			if (stripos($contentType, 'application/json') !== false) {
				$jsonBody = json_decode(file_get_contents('php://input'), true) ?? [];
				$data = array_merge($data, $jsonBody);
			} else {
				$data = array_merge($data, $_POST);
			}
		}
		
		return $key !== null ? ($data[$key] ?? $default) : $data;
	}

    /**
     * Получить GET-параметр (из $_GET).
     */
    public function query(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $_GET;
        }
        return $_GET[$key] ?? $default;
    }

    /**
     * Получить POST-параметр (из $_POST).
     */
    public function post(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $_POST;
        }
        return $_POST[$key] ?? $default;
    }

    /**
     * Получить все данные запроса (GET + POST).
     */
    public function input(?string $key = null, $default = null)
    {
        return $this->getParams($key, $default);
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
     * 
     * Используем внедрённые Audit, Session и Container
     */
    private function handleCsrfFailure(): void
    {
        // 1. Логируем попытку атаки
        // ✅ Используем внедрённый Audit (или получаем из контейнера)
        $audit = $this->audit ?? $this->container?->get(Audit::class);
        if ($audit !== null) {
            $audit->log('security.csrf_failed', 'Неверный CSRF-токен', 'security', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'url' => $_SERVER['REQUEST_URI'] ?? '/',
                'method' => $this->getMethod(),
                'referer' => $_SERVER['HTTP_REFERER'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        }

        // 2. Регенерируем токен (предотвращает повторное использование)
        unset($_SESSION[self::CSRF_TOKEN_KEY]);

        // 3. Flash-сообщение для пользователя
        // ✅ Используем внедрённый Session (или получаем из контейнера)
        $session = $this->session ?? $this->container?->get(Session::class);
        if ($session !== null) {
            $session->flash('error', 'Срок действия формы истёк. Пожалуйста, обновите страницу и попробуйте снова.');
        }

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

        // 5. Для обычных запросов — используем ErrorsController
        http_response_code(419);
        
        // ✅ Создаём ErrorsController через контейнер
        if ($this->container !== null) {
            $errorController = $this->container->make(\App\Modules\Errors\Controllers\ErrorsController::class);
        } else {
            // Fallback: создаём через new (если контейнер недоступен)
            $errorController = new \App\Modules\Errors\Controllers\ErrorsController();
        }
        
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
	
	/**
	 * Получить загруженный файл
	 * 
	 * @param string $key Имя поля файла
	 * @return array|null Данные файла или null
	 */
	public function file(string $key): ?array
	{
		return $_FILES[$key] ?? null;
	}

	/**
	 * Проверить наличие загруженного файла
	 */
	public function hasFile(string $key): bool
	{
		return isset($_FILES[$key]) && $_FILES[$key]['error'] !== UPLOAD_ERR_NO_FILE;
	}
	
	/**
	 * Получить HTTP заголовок
	 * 
	 * @param string $key Имя заголовка (например, 'HTTP_REFERER')
	 * @param mixed $default Значение по умолчанию
	 * @return mixed
	 */
	public function header(string $key, mixed $default = null): mixed
	{
		return $_SERVER[$key] ?? $default;
	}

	/**
	 * Получить все заголовки
	 */
	public function headers(): array
	{
		return array_filter($_SERVER, fn($key) => str_starts_with($key, 'HTTP_'), ARRAY_FILTER_USE_KEY);
	}
	
	/**
	 * Получить cookie
	 */
	public function cookie(string $key, mixed $default = null): mixed
	{
		return $_COOKIE[$key] ?? $default;
	}

	/**
	 * Проверить наличие cookie
	 */
	public function hasCookie(string $key): bool
	{
		return isset($_COOKIE[$key]);
	}

	/**
	 * Получить IP клиента
	 */
	public function getIp(): string
	{
		return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
	}

	/**
	 * Проверить, является ли запрос HTTPS
	 */
	public function isSecure(): bool
	{
		return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
	}
}