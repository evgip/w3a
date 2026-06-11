<?php

// 1. Сразу фиксируем точку старта для сбора статистики
require_once dirname(__DIR__) . '/app/Core/Benchmark.php';
\App\Core\Benchmark::start();

// 2. Включаем отображение ошибок
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Настройка безопасного поведения сессионных кук
//ini_set('session.cookie_httponly', 1); // Браузерный JS вообще не имеет доступа к куке (защита от XSS-угона)
//ini_set('session.cookie_secure', 1);   // Кука передается ТОЛЬКО по защищенному протоколу HTTPS
//ini_set('session.cookie_samesite', 'Strict'); // Защита от CSRF на уровне браузера (кука не улетает на сторонние сайты)
//ini_set('session.use_only_cookies', 1); // Исключает передачу ID сессии через URL (защита от Session Fixation)


// 3. Подключаем Composer
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Request;
use App\Core\Router;

try {
    $request = new Request();
    \App\Core\Lang::init();
    \App\Core\Security::sendCspHeader();
	
    // --- INTEGRATE FIREWALL BLOCKER INTERCEPTOR HERE ---
    \App\Core\Firewall::check();
    // ----------------------------------------------------

    $router = new Router($request);
    $router->dispatch();

} catch (\Throwable $e) {
    // 1. ЗАПИСЫВАЕМ ОШИБКУ В ТЕКСТОВЫЙ ЛОГ НА СЕРВЕРЕ
    $errorMessage = $e->getMessage() . " в файле " . $e->getFile() . " на строке " . $e->getLine();
    \App\Core\Logger::error($errorMessage, [
        'trace' => $e->getTraceAsString(),
        'url' => $_SERVER['REQUEST_URI'] ?? '/'
    ]);

    // 2. ОПРЕДЕЛЯЕМ, ЧТО ПОКАЗАТЬ ПОЛЬЗОВАТЕЛЮ
    $config = require dirname(__DIR__) . '/app/Config/config.php';
    $isDevelopment = ($config['app']['env'] ?? 'development') === 'development';

    http_response_code(500);

    if ($isDevelopment) {
        // Режим разработки: выводим ошибку на экран для программиста
        echo '<div style="background: #f8d7da; color: #721c24; padding: 20px; font-family: monospace; margin: 20px; border: 1px solid #f5c6cb;">';
        echo '<h2>💥 Ошибка разработки (Development Mode):</h2>';
        echo '<strong>Сообщение:</strong> ' . htmlspecialchars($e->getMessage()) . '<br><br>';
        echo '<strong>Файл:</strong> ' . htmlspecialchars($e->getFile()) . ' (строка ' . $e->getLine() . ')<br><br>';
        echo '<strong>Стек вызовов записан в app/Storage/logs/app.log</strong>';
        echo '</div>';
    } else {
        // Режим продакшена: полностью скрываем код, вызываем модуль ошибок
        $errorController = "App\\Modules\\Errors\\Controllers\\ErrorsController";
        if (class_exists($errorController)) {
            (new $errorController())->notFound("Извините, на сервере произошла внутренняя ошибка. Инженеры уже уведомлены.");
            exit;
        }
        echo "<h1>500 Internal Server Error</h1><p>Извините, на сервере произошла непредвиденная ошибка.</p>";
    }
}
