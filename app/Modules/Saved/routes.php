<?php
// app/Modules/Saved/routes.php

use App\Modules\Saved\Controllers\SavedController;

// Все маршруты требуют авторизации
$router->group(['middleware' => ['web', 'auth']], function ($router) {
    $router->add('GET', '/saved', SavedController::class . '@index', 'saved.index');
    $router->add('POST', '/saved/toggle/{id}', SavedController::class . '@toggle', 'saved.toggle');
});