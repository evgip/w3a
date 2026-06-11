<?php

namespace App\Core;

class Config
{
    private static ?array $settings = null;

    /**
     * Инициализация и загрузка файла конфигурации
     */
    private static function load(): void
    {
        if (self::$settings === null) {
            $configFile = dirname(__DIR__) . '/Config/config.php';
            if (file_exists($configFile)) {
                self::$settings = require $configFile;
            } else {
                self::$settings = [];
            }
        }
    }

    /**
     * Получить настройку по ключу. Поддерживает вложенность через точку (напр. 'app.name')
     */
    public static function get(string $key, $default = null)
    {
        self::load();

        $keys = explode('.', $key);
        $array = self::$settings;

        foreach ($keys as $k) {
            if (is_array($array) && isset($array[$k])) {
                $array = $array[$k];
            } else {
                return $default; // Возвращаем дефолтное значение, если путь не найден
            }
        }

        return $array;
    }
}
