<?php

/**
 * Глобальный хелпер для генерации URL по имени маршрута
 */
if (!function_exists('route')) {
    function route(string $name, array $params = []): string {
        global $router; // Используем глобальный объект роутера из index.php
        return $router->route($name, $params);
    }
}

/**
 * Глобальный хелпер для вывода локализованных строк перевода
 */
if (!function_exists('__')) {
    function __(string $key, array $replace = []): string {
        return \App\Core\Lang::get($key, $replace);
    }
}