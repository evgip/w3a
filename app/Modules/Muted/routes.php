<?php

use App\Modules\Muted\Controllers\MuteController;

$router->group(['middleware' => ['web', 'auth']], function ($router) {
    $router->add('GET', '/muted', MuteController::class . '@list', 'muted.list');
    $router->add('POST', '/mute/toggle/{id}', MuteController::class . '@toggle', 'mute.toggle');
});
