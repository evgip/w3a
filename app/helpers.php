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

function declension(int $number, array $forms): string
{
    $number = abs($number) % 100;
    $n1 = $number % 10;

    if ($number > 10 && $number < 20) {
        return $forms[2];
    }
    if ($n1 > 1 && $n1 < 5) {
        return $forms[1];
    }
    if ($n1 === 1) {
        return $forms[0];
    }
    return $forms[2];
}

/**
 * Подключение partial-шаблона из модуля
 * 
 * @param string $path   - путь вида 'Votes::_voters' или 'Users::_avatar'
 * @param array  $vars   - переменные для шаблона
 */
function partial(string $path, array $vars = []): void
{
    // Разбираем путь: "Votes::_voters" → модуль Votes, файл _voters.php
    [$module, $file] = explode('::', $path);
    $filePath = dirname(__DIR__) . "/app/Modules/{$module}/Views/{$file}.php";
    
    if (!file_exists($filePath)) {
        throw new \RuntimeException("Partial not found: {$filePath}");
    }
    
    // Извлекаем переменные в текущую область видимости
    extract($vars);
    include $filePath;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Форматирование даты
 */
function dt(?string $datetime, string $format = 'd.m.Y H:i'): string
{
    if (!$datetime) return '';
    return date($format, strtotime($datetime));
}

/**
 * Склонение числительных
 */
function plural(int $n, array $forms): string
{
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) return $forms[2];
    if ($n1 > 1 && $n1 < 5) return $forms[1];
    if ($n1 === 1) return $forms[0];
    return $forms[2];
}

/**
 * Конфиг (глобальный доступ без require)
 */
function config(string $key, $default = null)
{
    static $config = null;
    if ($config === null) {
        $config = require dirname(__DIR__) . '/Config/config.php';
    }
    return $config[$key] ?? $default;
}

if (!function_exists('csrf_field')) {
    /**
     * Генерирует скрытое поле с CSRF-токеном
     */
    function csrf_field(): string
    {
        $request = new \App\Core\Request();
        return $request->csrfField();
    }
}

/**
 * Вывод flash-сообщений
 */
function render_flashes(): void
{
    $types = [
        'success' => 'alert-success',
        'error'   => 'alert-danger',
        'notice'  => 'alert-notice'
    ];

    foreach ($types as $key => $class) {
        if (\App\Core\Session::hasFlash($key)) {
            $message = htmlspecialchars(\App\Core\Session::getFlash($key));
            $title = $key === 'success' ? 'Успех' : ($key === 'error' ? 'Ошибка' : 'Информация');
            
            echo '<div class="alert ' . $class . '">';
            echo '<strong>' . $title . '!</strong> ' . $message;
            echo '</div>';
        }
    }
}