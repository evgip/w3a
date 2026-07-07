<?php
// app/Modules/Rss/routes.php

use App\Modules\Rss\Controllers\RssController;

// Все маршруты публичные, без авторизации
$router->add('GET', '/rss', RssController::class . '@index', 'rss.index');
$router->add('GET', '/t/{tagslug}/rss', RssController::class . '@byTag', 'rss.tag');
$router->add('GET', '/u/{username}/rss', RssController::class . '@byUser', 'rss.user');
$router->add('GET', '/comments/rss', RssController::class . '@comments', 'rss.comments');
