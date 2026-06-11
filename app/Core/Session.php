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
            unset($_SESSION['flash'][$key]); // Стираем после прочтения
            return $message;
        }
        return null;
    }
}
