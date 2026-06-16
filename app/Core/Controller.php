<?php

namespace App\Core;

abstract class Controller
{
    protected function render(string $viewName, array $data = []): void
    {
		
		// Добавляем CSRF токен во все представления
		$data['csrf_token'] = (new \App\Core\Request())->getCsrfToken();
		
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
     * Требовать авторизацию пользователя.
     * Если не авторизован — перенаправляет на страницу входа.
     */
    protected function requireAuth(): void
    {
        if (!Auth::check()) {
            Session::setFlash('error', 'Пожалуйста, авторизуйтесь для доступа к этой странице.');
            $this->redirect('/login');
        }
    }

    /**
     * Требовать права администратора.
     */
    protected function requireAdmin(): void
    {
        $this->requireAuth();
        
        if (!Auth::isAdmin()) {
            http_response_code(403);
            die('<h1>403 Forbidden</h1><p>Доступ запрещён. Требуются права администратора.</p>');
        }
    }

    /**
     * Получить ID текущего авторизованного пользователя.
     * Автоматически вызывает requireAuth().
     * 
     * @return int
     */
    protected function currentUserId(): int
    {
        $this->requireAuth();
        return Auth::id();
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
     * Редирект на предыдущую страницу с ошибкой.
     */
    protected function backWithError(string $message): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $this->redirectWithError($referer, $message);
    }

    /**
     * Редирект на предыдущую страницу с сообщением об успехе.
     */
    protected function backWithSuccess(string $message): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $this->redirectWithSuccess($referer, $message);
    }
}
