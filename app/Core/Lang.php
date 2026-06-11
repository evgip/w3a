<?php

namespace App\Core;

class Lang
{
    protected static string $currentLang = 'ru';
    protected static array $translations = [];

    /**
     * Инициализация локализации и загрузка общих переводов
     */
    public static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Выбираем язык: из сессии, иначе из конфига приложения, дефолт — 'ru'
        $config = require dirname(__DIR__) . '/Config/config.php';
        self::$currentLang = $_SESSION['lang'] ?? $config['app']['lang'] ?? 'ru';

        // Загружаем общие переводы ядра
        $coreLangPath = dirname(__DIR__) . "/Lang/" . self::$currentLang . ".php";
        if (file_exists($coreLangPath)) {
            self::$translations = require $coreLangPath;
        }
    }

    /**
     * Смена языка пользователем
     */
    public static function change(string $lang): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['lang'] = $lang;
        self::$currentLang = $lang;
    }

    /**
     * Загрузка специфических переводов конкретного модуля
     */
    public static function loadModuleLang(string $moduleName): void
    {
        $moduleLangPath = dirname(__DIR__) . "/Modules/{$moduleName}/Lang/" . self::$currentLang . ".php";
        
        if (file_exists($moduleLangPath)) {
            $moduleTrans = require $moduleLangPath;
            // Сливаем массивы перевода, сохраняя ключи
            self::$translations = array_merge(self::$translations, $moduleTrans);
        }
    }

    /**
     * Получить перевод по ключу. Поддерживает вложенность через точку (напр. 'auth.login')
     */
    public static function get(string $key, array $replace = []): string
    {
        $keys = explode('.', $key);
        $array = self::$translations;

        foreach ($keys as $k) {
            if (isset($array[$k])) {
                $array = $array[$k];
            } else {
                return $key; // Если перевод не найден, возвращаем сам ключ
            }
        }

        $text = is_string($array) ? $array : $key;

        // Если переданы переменные для замены (например, ['name' => 'Иван'])
        if (!empty($replace)) {
            foreach ($replace as $placeholder => $value) {
                $text = str_replace(':' . $placeholder, $value, $text);
            }
        }

        return $text;
    }
	
    /**
     * Fetch a localized string and format it with dynamic parameters safely
     * 
     * @param string $key Dictionary key path
     * @param array $args Parameters injected into placeholders
     * @return string
     */
    public static function format(string $key, array $args = []): string
    {
        // Fall back to your core get method logic to pull the template text base
        $template = self::get($key);
        
        if (empty($args)) {
            return $template;
        }

        // Safely map values into %s positions array
        return sprintf($template, ...$args);
    }
}
