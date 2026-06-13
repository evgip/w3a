<?php

namespace App\Core;

class Session
{
    public static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Получить значение из сессии
     */
    public static function get(string $key, $default = null)
    {
        self::init();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Установить значение в сессии
     */
    public static function set(string $key, $value): void
    {
        self::init();
        $_SESSION[$key] = $value;
    }

    /**
     * Удалить значение из сессии
     */
    public static function delete(string $key): void
    {
        self::init();
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * Установить flash-сообщение
     */
    public static function setFlash(string $key, string $message): void
    {
        self::init();
        $_SESSION['flash'][$key] = $message;
    }

    /**
     * Проверить наличие flash-сообщения
     */
    public static function hasFlash(string $key): bool
    {
        self::init();
        return isset($_SESSION['flash'][$key]);
    }

    /**
     * Получить и сразу удалить flash-сообщение
     */
    public static function getFlash(string $key): ?string
    {
        self::init();
        if (isset($_SESSION['flash'][$key])) {
            $message = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $message;
        }
        return null;
    }
}