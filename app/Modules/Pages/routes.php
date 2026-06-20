<?php
/**
 * Маршруты модуля Pages (статические информационные страницы)
 * 
 * Все маршруты публичные — доступны всем, включая гостей.
 * Middleware не требуются (GET-запросы, без форм).
 * 
 * @var \App\Core\Router $router
 */

use App\Modules\Pages\Controllers\PagesController;

// =========================================================================
// ПУБЛИЧНЫЕ СТАТИЧЕСКИЕ СТРАНИЦЫ
// =========================================================================

/**
 * Страница "О проекте" — описание сайта, миссия, команда.
 */
$router->add(
    'GET', 
    '/about', 
    PagesController::class . '@about', 
    'page.about'
);

/**
 * Политика конфиденциальности — обработка персональных данных.
 */
$router->add(
    'GET', 
    '/privacy', 
    PagesController::class . '@privacy', 
    'page.privacy'
);

/**
 * Правила сообщества — нормы поведения, модерация.
 */
$router->add(
    'GET', 
    '/rules', 
    PagesController::class . '@rules', 
    'page.rules'
);

/**
 * Страница чата — правила и ссылки на внешние чаты.
 */
$router->add(
    'GET', 
    '/chat', 
    PagesController::class . '@chat', 
    'page.chat'
);