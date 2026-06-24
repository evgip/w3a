<?php

/**
 * Точка входа приложения
 */

// ═══════════════════════════════════════════
// 1. ЗАГРУЗКА .ENV (САМОЕ ПЕРВОЕ!)
// ═══════════════════════════════════════════

require_once dirname(__DIR__) . '/app/Core/Env.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

// ═══════════════════════════════════════════
// 2. АВТОЗАГРУЗКА COMPOSER
// ═══════════════════════════════════════════

require_once dirname(__DIR__) . '/vendor/autoload.php';

// ═══════════════════════════════════════════
// 3. 🔐 НАСТРОЙКИ БЕЗОПАСНОСТИ СЕССИИ
// ═══════════════════════════════════════════
    $isProduction = env('APP_ENV', 'development') === 'production';
	

if (session_status() === PHP_SESSION_NONE) {
    $isProduction = env('APP_ENV', 'development') === 'production';

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || ($_SERVER['SERVER_PORT'] ?? 80) == 443;

    $useSecure = ($isProduction && $isHttps);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $useSecure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    $sessionName = env('SESSION_NAME', 'w3a_session');
    session_name($sessionName);
}

// ═══════════════════════════════════════════
// 4. ЗАПУСК ПРИЛОЖЕНИЯ
// ═══════════════════════════════════════════

$app = new \App\Core\Application();
$app->bootstrap()->run();