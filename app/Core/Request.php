<?php

namespace App\Core;

/**
 * Класс для работы с HTTP-запросом.
 * 
 * Audit, Session и Container внедряются через конструктор.
 * CSRF-защита через Double-Submit Cookie Pattern
 */
class Request
{
    private const CSRF_TOKEN_KEY = 'csrf_token';           // Ключ в сессии
    private const CSRF_TOKEN_NAME = 'csrf_token';          // Имя поля в POST
    private const CSRF_COOKIE_NAME = 'XSRF-TOKEN';         // Имя cookie
    private const CSRF_HEADER_NAME = 'HTTP_X_XSRF_TOKEN';  // Заголовок для AJAX

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
	 * Получить или сгенерировать CSRF-токен (Double-Submit Cookie Pattern)
	 * 
	 * Токен хранится в cookie (доступен JS) и в сессии (для серверной проверки).
	 * Cookie устанавливается с SameSite=Strict для защиты от CSRF.
	 */
	public function getCsrfToken(): string
	{
		// Убеждаемся, что сессия запущена
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}

		// 1. Проверяем cookie
		$cookieToken = $_COOKIE[self::CSRF_COOKIE_NAME] ?? null;
		
		// 2. Проверяем сессию
		$sessionToken = $_SESSION[self::CSRF_TOKEN_KEY] ?? null;

		// 3. Если оба есть и совпадают — используем их
		if ($cookieToken && $sessionToken && hash_equals($cookieToken, $sessionToken)) {
			return $cookieToken;
		}

		// 4. Если cookie есть, но сессия пустая/не совпадает — синхронизируем сессию из cookie
		if ($cookieToken && !$sessionToken) {
			$_SESSION[self::CSRF_TOKEN_KEY] = $cookieToken;
			return $cookieToken;
		}

		// 5. Если cookie нет или не совпадает с сессией — генерируем новый токен
		$token = bin2hex(random_bytes(32));
		$this->setCsrfCookie($token);
		$_SESSION[self::CSRF_TOKEN_KEY] = $token;

		return $token;
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
     * Валидация CSRF-токена (Double-Submit Cookie Pattern)
     */
	public function validateCsrf(): void
	{
		// GET-запросы не требуют CSRF
		if ($this->isGet()) {
			return;
		}

		// 1. Получаем токен из cookie
		$cookieToken = $_COOKIE[self::CSRF_COOKIE_NAME] ?? '';

		// 2. Получаем токен из запроса (заголовок или POST-параметр)
		$requestToken = $this->getCsrfTokenFromRequest();

		// 3. Double-submit проверка: cookie == запрос
		if (
			empty($cookieToken) || empty($requestToken) ||
			!hash_equals((string)$cookieToken, (string)$requestToken)
		) {
			$this->handleCsrfFailure();
			return;
		}

		// 4. Дополнительная проверка: токен есть в сессии (опционально, можно убрать)
		// Теперь getCsrfToken() гарантирует синхронизацию, так что эта проверка избыточна
		// Но оставим для дополнительной безопасности
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}
		$sessionToken = $_SESSION[self::CSRF_TOKEN_KEY] ?? '';
		if (empty($sessionToken) || !hash_equals((string)$sessionToken, (string)$cookieToken)) {
			$this->handleCsrfFailure();
			return;
		}

		// 5. Ротация токена после успешной проверки
		$this->regenerateCsrfToken();
	}

    /**
     * Валидация CSRF без редиректа (для AJAX-запросов)
     */
    public function isCsrfValid(): bool
    {
        $cookieToken = $_COOKIE[self::CSRF_COOKIE_NAME] ?? '';
        $requestToken = $this->getCsrfTokenFromRequest();

        if (empty($cookieToken) || empty($requestToken)) {
            return false;
        }

        return hash_equals($cookieToken, $requestToken);
    }

    /**
     * Получает токен из запроса (заголовок или POST)
     * Приоритет: заголовок (для AJAX) > POST-параметр (для форм)
     */
    private function getCsrfTokenFromRequest(): string
    {
        // Приоритет 1: заголовок (для AJAX)
        $headerToken = $this->header(self::CSRF_HEADER_NAME);
        if ($headerToken) {
            return $headerToken;
        }

        // Приоритет 2: POST-параметр (для форм)
        return $this->getParams(self::CSRF_TOKEN_NAME) ?? '';
    }

    /**
     * Регенерация CSRF-токена
     * Вызывается после успешной проверки для обеспечения одноразовости
     */
    public function regenerateCsrfToken(): void
    {
        // Генерируем новый токен
        $token = bin2hex(random_bytes(32));

        // Обновляем cookie
        $this->setCsrfCookie($token);

        // Обновляем сессию
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION[self::CSRF_TOKEN_KEY] = $token;
    }

    /**
     * Устанавливает CSRF cookie с безопасными параметрами
     */
    private function setCsrfCookie(string $token): void
    {
        setcookie(self::CSRF_COOKIE_NAME, $token, [
            'expires' => time() + (7 * 24 * 60 * 60), // 7 дней
            'path' => '/',
            'secure' => $this->isSecure(),
            'httponly' => false,  // JS должен читать cookie
            'samesite' => 'Strict' // Защита от CSRF
        ]);
    }

    /**
     * Обработка провала CSRF-валидации
     * 
     * Используем внедрённые Audit, Session и Container
     */
    private function handleCsrfFailure(): void
    {
        // 1. Логируем попытку атаки
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
        unset($_COOKIE[self::CSRF_COOKIE_NAME]);
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION[self::CSRF_TOKEN_KEY]);

        // 3. Flash-сообщение для пользователя
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

        // 5. ✅ ИСПРАВЛЕНО: Для обычных запросов — используем контейнер
        http_response_code(419);

        if ($this->container === null) {
            // Критическая ошибка — контейнер должен быть всегда доступен
            exit('CSRF validation failed. Please reload the page.');
        }

        $errorController = $this->container->make(\App\Modules\Errors\Controllers\ErrorsController::class);
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

    /**
     * Получить User-Agent клиента
     */
    public function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
}
