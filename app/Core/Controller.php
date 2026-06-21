<?php

namespace App\Core;

use App\Core\Events\Event;
use App\Core\Events\EventDispatcher;

abstract class Controller
{
    protected Request $request;
    protected EventDispatcher $eventDispatcher;
    
    /**
     * Конструктор базового контроллера.
     * Параметры опциональны для обратной совместимости.
     */
    public function __construct(?Request $request = null, ?EventDispatcher $eventDispatcher = null)
    {
        $this->request = $request ?? new Request();
        
        // Если EventDispatcher передан из Router — используем его
        // Иначе создаем новый (для ручного создания контроллеров)
        if ($eventDispatcher !== null) {
            $this->eventDispatcher = $eventDispatcher;
        } else {
            $this->eventDispatcher = new EventDispatcher();
            // Регистрируем слушателей только для новых экземпляров
            \App\Providers\EventServiceProvider::register($this->eventDispatcher);
        }
    }
    
    protected function dispatch(Event $event): void
    {
        $this->eventDispatcher->dispatch($event);
    }
    
    protected function render(string $viewName, array $data = []): void
    {
        $data['csrf_token'] = $this->request->getCsrfToken();
        
        $calledClass = get_called_class();
        $parts = explode('\\', $calledClass);
        $moduleName = $parts[2] ?? ''; 
        
        if (!empty($moduleName)) {
            \App\Core\Lang::loadModuleLang($moduleName);
        }
        
        $modulePath = dirname(__DIR__) . "/Modules/{$moduleName}";
        $viewFile = "{$modulePath}/Views/{$viewName}.php";
        $layoutFile = "{$modulePath}/Views/layout.php";
        
        if (!file_exists($viewFile)) {
            http_response_code(500);
            $errorController = "App\\Modules\\Errors\\Controllers\\ErrorsController";
            if (class_exists($errorController)) {
                (new $errorController())->serverError("Внутренняя ошибка сервера.");
                exit;
            }
            die("<h1>500 Internal Server Error</h1>");
        }
        
        ob_start();
        (function() use ($data, $viewFile) {
            extract($data, EXTR_SKIP);
            include $viewFile;
        })();
        $content = ob_get_clean();
        
        if (file_exists($layoutFile)) {
            include $layoutFile;
        } else {
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
    
    protected function redirect(string $url, int $code = 302): void
    {
        http_response_code($code);
        header('Location: ' . $url);
        exit;
    }
    
    protected function redirectBack(string $fallback = '/'): void
    {
        $this->redirect($this->getSafeBackUrl($fallback));
    }
    
    private function getSafeBackUrl(string $fallback = '/'): string
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? $fallback;
        return $this->isSafeUrl($referer) ? $referer : $fallback;
    }
    
    private function isSafeUrl(string $url): bool
    {
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }
        
        $urlHost = parse_url($url, PHP_URL_HOST);
        if ($urlHost === null) {
            return false;
        }
        
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        return $urlHost === $appHost;
    }
    
    protected function redirectWithError(string $url, string $message): void
    {
        Session::setFlash('error', $message);
        $this->redirect($url);
    }
    
    protected function redirectWithSuccess(string $url, string $message): void
    {
        Session::setFlash('success', $message);
        $this->redirect($url);
    }
    
    protected function backWithError(string $message, string $fallback = '/'): void
    {
        $this->redirectWithError($this->getSafeBackUrl($fallback), $message);
    }
    
    protected function backWithSuccess(string $message, string $fallback = '/'): void
    {
        $this->redirectWithSuccess($this->getSafeBackUrl($fallback), $message);
    }
}