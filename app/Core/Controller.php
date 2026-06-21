<?php

namespace App\Core;

use App\Core\Events\Event;
use App\Core\Events\EventDispatcher;
use App\Providers\EventServiceProvider;

abstract class Controller
{
	protected Request $request;
	protected EventDispatcher $eventDispatcher;
	
    /**
     * Конструктор базового контроллера.
     * Параметр опционален для обратной совместимости
     * (например, при ручном создании ErrorsController).
     */
    public function __construct(?Request $request = null)
    {
        $this->request = $request ?? new Request();
		
        // Создаём EventDispatcher и регистрируем слушателей
        $this->eventDispatcher = $eventDispatcher ?? new EventDispatcher();
        \App\Providers\EventServiceProvider::register($this->eventDispatcher);
    }
	
	
    /**
     * Отправить событие.
     */
    protected function dispatch(Event $event): void
    {
        $this->eventDispatcher->dispatch($event);
    }
	
    protected function render(string $viewName, array $data = []): void
    {
        // БЫЛО: $data['csrf_token'] = (new \App\Core\Request())->getCsrfToken();
        // СТАЛО: используем уже созданный инстанс
        $data['csrf_token'] = $this->request->getCsrfToken();
		
        extract($data);

        $calledClass = get_called_class();
        $parts = explode('\\', $calledClass);
        $moduleName = $parts[2] ?? ''; 

        if (!empty($moduleName)) {
            \App\Core\Lang::loadModuleLang($moduleName);
        }

        $modulePath = dirname(__DIR__) . "/Modules/{$moduleName}";
        $viewFile = "{$modulePath}/Views/{$viewName}.php";
        $layoutFile = "{$modulePath}/Views/layout.php";

        // БЕЗОПАСНО: Скрываем системные пути от пользователей
        if (!file_exists($viewFile)) {
            http_response_code(500);
            
            // В продакшене отдаем красивую ошибку, скрывая реальный путь к файлу
            $errorController = "App\\Modules\\Errors\\Controllers\\ErrorsController";
            if (class_exists($errorController)) {
                (new $errorController())->notFound("Внутренняя ошибка сервера. Шаблон отображения недоступен.");
                exit;
            }
            die("<h1>500 Internal Server Error</h1>");
        }

        ob_start();
        include $viewFile;
        $content = ob_get_clean();

        // Умный поиск лэйаута (Перенаправлен на модуль Common)
        if (file_exists($layoutFile)) {
            include $layoutFile;
        } else {
            // ФОЛБЭК: Если локального лэйаута нет, берем главный каркас из модуля НАСТРОЕК ПОЛЬЗОВАТЕЛЕЙ
            $fallbackLayout = dirname(__DIR__) . '/Modules/Common/Views/layout.php';
            
            if (file_exists($fallbackLayout)) {
                include $fallbackLayout;
            } else {
                echo $content;
            }
        }
    }

    protected function json(array $data, int $statusCode = 200): void
    {
        if (ob_get_length()) {
            ob_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
	
    /**
     * Безопасный редирект "назад" с защитой от open redirect
     */
    protected function redirectBack(string $fallback = '/'): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? $fallback;
        
        // Валидация: разрешаем только относительные URL или свой домен
        if (!$this->isSafeUrl($referer, $fallback)) {
            $referer = $fallback;
        }
        
        $this->redirect($referer);
    }
    
    /**
     * Простой редирект (уже должен быть в базовом классе)
     */
    protected function redirect(string $url, int $code = 302): void
    {
        http_response_code($code);
        header('Location: ' . $url);
        exit;
    }
    
    /**
     * Проверка безопасности URL
     */
    private function isSafeUrl(string $url, string $fallback): bool
    {
        // 1. Относительные URL (начинаются с /) — всегда безопасны
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }
        
        // 2. Проверяем хост на совпадение с нашим доменом
        $urlHost = parse_url($url, PHP_URL_HOST);
        if ($urlHost === null) {
            return false; // нет хоста и не относительный — подозрительно
        }
        
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        return $urlHost === $appHost;
    }

    /**
     * Редирект с flash-сообщением об ошибке.
     */
    protected function redirectWithError(string $url, string $message): void
    {
        Session::setFlash('error', $message);
        $this->redirect($url);
    }

    /**
     * Редирект с flash-сообщением об успехе.
     */
    protected function redirectWithSuccess(string $url, string $message): void
    {
        Session::setFlash('success', $message);
        $this->redirect($url);
    }

    /**
     * Безопасный редирект "назад" с защитой от open redirect
     */
    protected function safeBack(string $fallback = '/'): string
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? $fallback;
        
        // Разрешаем только относительные URL или свой домен
        if (!$this->isSafeRedirectUrl($referer)) {
            return $fallback;
        }
        
        return $referer;
    }

    /**
     * Проверка безопасности URL для редиректа
     */
    private function isSafeRedirectUrl(string $url): bool
    {
        // Относительные пути (начинаются с /, но не с //) — безопасны
        if (preg_match('#^/[^/]#', $url) || $url === '/') {
            return true;
        }
        
        // Абсолютные URL — только свой домен
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === null) {
            return false;
        }
        
        $appHost = parse_url(config('app.url') ?? '', PHP_URL_HOST);
        return $appHost && $host === $appHost;
    }

    /**
     * Редирект на предыдущую страницу с ошибкой (безопасно)
     */
    protected function backWithError(string $message, string $fallback = '/'): void
    {
        $this->redirectWithError($this->safeBack($fallback), $message);
    }

    /**
     * Редирект на предыдущую страницу с успехом (безопасно)
     */
    protected function backWithSuccess(string $message, string $fallback = '/'): void
    {
        $this->redirectWithSuccess($this->safeBack($fallback), $message);
    }
}
