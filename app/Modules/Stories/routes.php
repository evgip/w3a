<?php
/**
 * Маршруты модуля Stories
 * 
 * @var \App\Core\Router $router
 */

use App\Modules\Stories\Controllers\StoriesController;

// =========================================================================
// ПУБЛИЧНЫЕ МАРШРУТЫ (доступны всем)
// =========================================================================

// Главная страница - лента историй
$router->add('GET', '/', StoriesController::class . '@index', 'home');
$router->add('GET', '/hot', StoriesController::class . '@index', 'stories.hot');
$router->add('GET', '/new', StoriesController::class . '@index', 'stories.new');
$router->add('GET', '/top', StoriesController::class . '@index', 'stories.top');

// Просмотр конкретной истории и комментариев
$router->add('GET', '/story/{id}', StoriesController::class . '@show', 'story.show');

// Фильтр по тегу
$router->add('GET', '/t/{tagname}', StoriesController::class . '@index', 'tags.filter');

// Фильтр по домену
$router->add('GET', '/domain/{domain}', StoriesController::class . '@index', 'domain.show');

// =========================================================================
// МАРШРУТЫ ДЛЯ АВТОРИЗОВАННЫХ ПОЛЬЗОВАТЕЛЕЙ
// =========================================================================

$router->group(['middleware' => ['web', 'auth']], function($router) {
    
    // --- Создание и редактирование историй ---
    $router->add('GET', '/stories/create', StoriesController::class . '@showCreateForm', 'story.form');
    $router->add('POST', '/stories/create', StoriesController::class . '@create', 'story.create');
    
    $router->add('GET', '/stories/{id}/edit', StoriesController::class . '@showEditForm', 'story.edit');
    $router->add('POST', '/stories/{id}/edit', StoriesController::class . '@update', 'story.edit.submit');
    
    // --- Работа с комментариями ---
    $router->add('POST', '/comments/create', StoriesController::class . '@addComment', 'comment.create');
    $router->add('POST', '/comments/{id}/edit', StoriesController::class . '@editComment', 'comment.edit');
    $router->add('POST', '/comments/{id}/delete', StoriesController::class . '@deleteComment', 'comment.delete');
    $router->add('POST', '/comments/{id}/restore', StoriesController::class . '@restoreComment', 'comment.restore');
    
    // --- Подписки и прочтение ---
    $router->add('POST', '/story/{id}/follow', StoriesController::class . '@toggleFollow', 'story.toggle.follow');
    $router->add('POST', '/story/{id}/mark-read', StoriesController::class . '@markRead', 'story.markRead');
});

// =========================================================================
// МАРШРУТЫ ДЛЯ АДМИНИСТРАТОРОВ
// =========================================================================

$router->group(['middleware' => ['web', 'admin']], function($router) {
    
    // Административные действия с историями
    $router->add('POST', '/admin/stories/{id}/delete', StoriesController::class . '@adminDelete', 'admin.story.delete');
    $router->add('POST', '/admin/stories/{id}/restore', StoriesController::class . '@adminRestore', 'admin.story.restore');
});