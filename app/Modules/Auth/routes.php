<?php

declare(strict_types=1);

use App\Modules\Auth\Controllers\AuthController;

// =========================================================================
// ПУБЛИЧНЫЕ МАРШРУТЫ (доступны всем, включая гостей)
// =========================================================================

/**
 * Активация аккаунта по токену из email.
 */
$router->add(
    'GET', 
    '/register/activate/{token}', 
    AuthController::class . '@activateAccount', 
    'auth.activate'
);

/**
 * Форма запроса ссылки для восстановления пароля.
 */
$router->add(
    'GET', 
    '/password/reset', 
    AuthController::class . '@showRequestResetForm', 
    'password.request'
);

/**
 * Форма ввода нового пароля (по токену из email).
 */
$router->add(
    'GET', 
    '/password/reset/{token}', 
    AuthController::class . '@showResetPasswordForm', 
    'password.reset'
);

/**
 * Альтернативный URL для восстановления пароля.
 */
$router->add(
    'GET', 
    '/password/recovery', 
    AuthController::class . '@showRequestResetForm', 
    'password.recovery'
);

// -------------------------------------------------------------------------
// POST-маршруты восстановления пароля (с CSRF-защитой)
// -------------------------------------------------------------------------

$router->group(['middleware' => ['web']], function($router) {
    
    /**
     * Отправка ссылки для восстановления на email.
     */
    $router->add(
        'POST', 
        '/password/reset', 
        AuthController::class . '@sendResetLink', 
        'password.request.submit'
    );

    /**
     * Обработка смены пароля.
     */
    $router->add(
        'POST', 
        '/password/reset/submit', 
        AuthController::class . '@executePasswordReset', 
        'password.reset.submit'
    );

    /**
     * Альтернативный URL (POST).
     */
    $router->add(
        'POST', 
        '/password/recovery', 
        AuthController::class . '@sendResetLink', 
        'password.recovery.submit'
    );
});

// =========================================================================
// МАРШРУТЫ ДЛЯ ГОСТЕЙ (только для неавторизованных)
// =========================================================================

$router->group(['middleware' => ['web', 'guest']], function($router) {
    
    $router->add('GET', '/login', AuthController::class . '@showLoginForm', 'auth.login');
    $router->add('POST', '/login', AuthController::class . '@login', 'auth.login.submit');
    
    $router->add('GET', '/register', AuthController::class . '@showRegisterForm', 'auth.register');
    $router->add('POST', '/register', AuthController::class . '@register', 'auth.register.submit');
});

// =========================================================================
// МАРШРУТЫ ДЛЯ АВТОРИЗОВАННЫХ
// =========================================================================

$router->group(['middleware' => ['web', 'auth']], function($router) {
    
    /**
     * Выход из аккаунта (POST для защиты от CSRF).
     */
    $router->add('POST', '/logout', AuthController::class . '@logout', 'auth.logout');
});