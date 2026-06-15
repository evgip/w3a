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
}
