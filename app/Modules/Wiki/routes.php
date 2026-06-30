<?php

/**
 * Маршруты модуля Wiki (вложенные в теги)
 *
 * ВАЖНО: Порядок маршрутов имеет значение!
 * Более специфичные маршруты должны идти первыми.
 *
 * @var App\Core\Router $router
 */

use App\Modules\Wiki\Controllers\WikiController;

// =========================================================================
// МАРШРУТЫ ДЛЯ АВТОРИЗОВАННЫХ (специфичные маршруты ПЕРВЫМИ!)
// =========================================================================

$router->group(['middleware' => ['web', 'auth']], function ($router) {
    
    // === СПЕЦИФИЧНЫЕ МАРШРУТЫ (без параметров в конце) ===

    /**
     * Форма создания wiki страницы для тега
     */
    $router->add(
        'GET',
        '/t/{tag}/wiki/create',
        WikiController::class . '@showCreateForm',
        'wiki.tag.create'
    );

    /**
     * Сохранение новой wiki страницы для тега
     */
    $router->add(
        'POST',
        '/t/{tag}/wiki/store',
        WikiController::class . '@create',
        'wiki.tag.store'
    );

    /**
     * Поиск по wiki тега
     */
    $router->add(
        'GET',
        '/t/{tag}/wiki/search',
        WikiController::class . '@search',
        'wiki.tag.search'
    );

    /**
     * Управление правами на wiki тега
     */
    $router->add(
        'GET',
        '/t/{tag}/wiki/permissions',
        WikiController::class . '@permissions',
        'wiki.tag.permissions'
    );

    $router->add(
        'POST',
        '/t/{tag}/wiki/permissions/grant',
        WikiController::class . '@grantPermission',
        'wiki.tag.permissions.grant'
    );

    $router->add(
        'POST',
        '/t/{tag}/wiki/permissions/revoke',
        WikiController::class . '@revokePermission',
        'wiki.tag.permissions.revoke'
    );

    /**
     * Форма редактирования wiki страницы
     */
    $router->add(
        'GET',
        '/t/{tag}/wiki/{id}/edit',
        WikiController::class . '@showEditForm',
        'wiki.tag.edit'
    );

    /**
     * Обновление wiki страницы
     */
    $router->add(
        'POST',
        '/t/{tag}/wiki/{id}/update',
        WikiController::class . '@update',
        'wiki.tag.update'
    );

    /**
     * Удаление wiki страницы
     */
    $router->add(
        'POST',
        '/t/{tag}/wiki/{id}/delete',
        WikiController::class . '@delete',
        'wiki.tag.delete'
    );
});

// =========================================================================
// ПУБЛИЧНЫЕ МАРШРУТЫ (с параметрами в конце - ПОСЛЕДНИМИ!)
// =========================================================================

/**
 * Список wiki страниц для тега
 */
$router->add(
    'GET',
    '/t/{tag}/wiki',
    WikiController::class . '@index',
    'wiki.tag.index'
);

/**
 * Просмотр wiki страницы тега
 */
$router->add(
    'GET',
    '/t/{tag}/wiki/{slug}',
    WikiController::class . '@show',
    'wiki.tag.show'
);
