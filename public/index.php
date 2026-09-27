<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// 1. Загрузка переменных окружения
\W3a\Core\Foundation\Env::load(dirname(__DIR__) . '/.env');

$basePath = dirname(__DIR__);
$config = new \W3a\Core\Foundation\Config(
    $basePath . '/app/Config',
    $basePath . '/vendor/evgip/w3a-core/config'
);
$session = new \W3a\Core\Http\Session($config->getArray('session', []));
$session->start();

// 2. Запуск приложения
$app = new \W3a\Core\Foundation\Application($basePath, [
    \W3a\Core\Foundation\CoreServiceProvider::class,
    \App\AppServiceProvider::class,
]);

$app->bootstrap()->run();