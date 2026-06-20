<?php
/**
 * Маршруты модуля Users (пользователи, авторизация, профиль)
 * 
 * Четыре группы доступа:
 * - Публичные: профили, восстановление пароля, активация
 * - Гости: вход и регистрация (авторизованные редиректятся на главную)
 * - Авторизованные: настройки, выход, управление аккаунтом
 * 
 * @var \App\Core\Router $router
 */

use App\Modules\Users\Controllers\UsersController;

// =========================================================================
// ПУБЛИЧНЫЕ МАРШРУТЫ (доступны всем, включая гостей)
// =========================================================================

/**
 * Список пользователей (публичный каталог).
 */
$router->add(
    'GET', 
    '/users', 
    UsersController::class . '@index', 
    'users.index'
);

/**
 * Публичный профиль пользователя.
 * 
 * @param string $username URL-имя пользователя
 */
$router->add(
    'GET', 
    '/user/{username}', 
    UsersController::class . '@profile', 
    'user.profile'
);

/**
 * Активация аккаунта по токену из email.
 * 
 * @param string $token Уникальный токен активации
 */
$router->add(
    'GET', 
    '/register/activate/{token}', 
    UsersController::class . '@activateAccount', 
    'auth.activate'
);

// -------------------------------------------------------------------------
// Восстановление пароля (публичное — доступно всем)
// -------------------------------------------------------------------------

/**
 * Форма запроса ссылки для восстановления пароля.
 */
$router->add(
    'GET', 
    '/password/reset', 
    UsersController::class . '@showRequestResetForm', 
    'password.request'
);

/**
 * Отправка ссылки для восстановления на email.
 */
$router->add(
    'POST', 
    '/password/reset', 
    UsersController::class . '@sendResetLink', 
    'password.request.submit'
);

/**
 * Форма ввода нового пароля (по токену из email).
 * 
 * @param string $token Уникальный токен восстановления
 */
$router->add(
    'GET', 
    '/password/reset/{token}', 
    UsersController::class . '@showResetPasswordForm', 
    'password.reset'
);

/**
 * Обработка смены пароля.
 * 
 * ⚠️ ВАЖНО: конкретный маршрут ДОЛЖЕН идти ДО параметрических,
 * иначе 'submit' будет интерпретирован как {token}.
 */
$router->add(
    'POST', 
    '/password/reset/submit', 
    UsersController::class . '@executePasswordReset', 
    'password.reset.submit'
);

/**
 * Альтернативный URL для восстановления пароля.
 * Ведёт на те же методы, что и /password/reset — для совместимости
 * со старыми ссылками в письмах и закладках.
 */
$router->add(
    'GET', 
    '/password/recovery', 
    UsersController::class . '@showRequestResetForm', 
    'password.recovery'
);

$router->add(
    'POST', 
    '/password/recovery', 
    UsersController::class . '@sendResetLink', 
    'password.recovery.submit'
);

// =========================================================================
// МАРШРУТЫ ДЛЯ ГОСТЕЙ (только для неавторизованных)
// =========================================================================

$router->group(['middleware' => ['web', 'guest']], function($router) {
    
    /**
     * Форма входа.
     */
    $router->add(
        'GET', 
        '/login', 
        UsersController::class . '@showLoginForm', 
        'auth.login'
    );
    
    /**
     * Обработка входа.
     */
    $router->add(
        'POST', 
        '/login', 
        UsersController::class . '@login', 
        'login.submit'
    );
    
    /**
     * Форма регистрации.
     */
    $router->add(
        'GET', 
        '/register', 
        UsersController::class . '@showRegisterForm', 
        'auth.register'
    );
    
    /**
     * Обработка регистрации.
     */
    $router->add(
        'POST', 
        '/register', 
        UsersController::class . '@register', 
        'register.submit'
    );
});

// =========================================================================
// МАРШРУТЫ ДЛЯ АВТОРИЗОВАННЫХ ПОЛЬЗОВАТЕЛЕЙ
// =========================================================================

$router->group(['middleware' => ['web', 'auth']], function($router) {
    
    // -------------------------------------------------------------------------
    // Выход из системы
    // -------------------------------------------------------------------------
    
    /**
     * Выход из аккаунта (с очисткой сессии).
     */
    $router->add(
        'GET', 
        '/logout', 
        UsersController::class . '@logout', 
        'auth.logout'
    );
    
    // -------------------------------------------------------------------------
    // Настройки аккаунта
    // -------------------------------------------------------------------------
    
    /**
     * Страница настроек аккаунта.
     */
    $router->add(
        'GET', 
        '/account/settings', 
        UsersController::class . '@settings', 
        'account.settings'
    );
    
    /**
     * Обновление основных настроек (email, имя и т.д.).
     */
    $router->add(
        'POST', 
        '/account/settings', 
        UsersController::class . '@updateSettings', 
        'account.settings.submit'
    );
    
    /**
     * Смена пароля.
     */
    $router->add(
        'POST', 
        '/account/settings/password', 
        UsersController::class . '@updatePassword', 
        'account.password.submit'
    );
    
    // -------------------------------------------------------------------------
    // Уведомления
    // -------------------------------------------------------------------------
    
    /**
     * Отметить все уведомления как прочитанные.
     */
    $router->add(
        'POST', 
        '/account/notifications/read', 
        UsersController::class . '@clearNotifications', 
        'account.notifications.read'
    );
});