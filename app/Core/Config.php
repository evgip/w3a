<?php

namespace App\Core;

/**
 * Универсальный класс для работы с конфигурацией
 * 
 * Поддерживает:
 * - Множественные файлы конфигурации
 * - Dot notation для вложенных ключей
 * - Кэширование загруженных файлов
 * - Разные типы данных (int, string, bool, array)
 * - Значения по умолчанию
 * 
 * Примеры использования:
 *   Config::get('config.app.name')                    // 'w3a'
 *   Config::get('config.app.min_karma_for_downvote')  // 10
 *   Config::get('constants.pagination.stories_per_page') // 15
 *   Config::get('database.host', 'localhost')         // 'localhost'
 */
class Config
{
    /**
     * Кэш загруженных конфигурационных файлов
     */
    private static array $cache = [];
    
    /**
     * Путь к директории с конфигурацией
     */
    private static string $configPath = '';
    
    /**
     * Инициализация пути к конфигурации
     */
    private static function initPath(): void
    {
        if (empty(self::$configPath)) {
            self::$configPath = dirname(__DIR__) . '/Config';
        }
    }
    
    /**
     * Загрузить конфигурационный файл
     * 
     * @param string $file Имя файла без расширения (например, 'config', 'constants', 'database')
     * @return array Загруженная конфигурация
     * @throws \Exception Если файл не найден
     */
    private static function loadFile(string $file): array
    {
        // Проверяем кэш
        if (isset(self::$cache[$file])) {
            return self::$cache[$file];
        }
        
        self::initPath();
        
        $filePath = self::$configPath . '/' . $file . '.php';
        
        if (!file_exists($filePath)) {
            throw new \Exception("Configuration file not found: {$filePath}");
        }
        
        $config = require $filePath;
        
        if (!is_array($config)) {
            throw new \Exception("Configuration file must return an array: {$file}.php");
        }
        
        // Кэшируем загруженный файл
        self::$cache[$file] = $config;
        
        return $config;
    }
    
    /**
     * Получить значение из конфигурации
     * 
     * @param string $key Ключ в формате 'file.group.key' или 'file.key'
     * @param mixed $default Значение по умолчанию
     * @return mixed
     * 
     * Примеры:
     *   Config::get('config.app.name')
     *   Config::get('config.app.min_karma_for_downvote', 10)
     *   Config::get('constants.pagination.stories_per_page')
     *   Config::get('database.host', 'localhost')
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        
        if (count($parts) < 2) {
            return $default;
        }
        
        // Первый элемент - имя файла конфигурации
        $file = array_shift($parts);
        
        try {
            $config = self::loadFile($file);
        } catch (\Exception $e) {
            return $default;
        }
        
        // Проходим по вложенным ключам
        foreach ($parts as $part) {
            if (!is_array($config) || !array_key_exists($part, $config)) {
                return $default;
            }
            $config = $config[$part];
        }
        
        return $config;
    }
    
    /**
     * Получить целое число из конфигурации
     * 
     * @param string $key Ключ конфигурации
     * @param int $default Значение по умолчанию
     * @return int
     */
    public static function getInt(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);
        return (int)$value;
    }
    
    /**
     * Получить строку из конфигурации
     * 
     * @param string $key Ключ конфигурации
     * @param string $default Значение по умолчанию
     * @return string
     */
    public static function getString(string $key, string $default = ''): string
    {
        $value = self::get($key, $default);
        return (string)$value;
    }
    
    /**
     * Получить булево значение из конфигурации
     * 
     * @param string $key Ключ конфигурации
     * @param bool $default Значение по умолчанию
     * @return bool
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);
        return (bool)$value;
    }
    
    /**
     * Получить массив из конфигурации
     * 
     * @param string $key Ключ конфигурации
     * @param array $default Значение по умолчанию
     * @return array
     */
    public static function getArray(string $key, array $default = []): array
    {
        $value = self::get($key, $default);
        return is_array($value) ? $value : $default;
    }
    
    /**
     * Проверить существование ключа
     * 
     * @param string $key Ключ конфигурации
     * @return bool
     */
    public static function has(string $key): bool
    {
        $sentinel = new \stdClass();
        return self::get($key, $sentinel) !== $sentinel;
    }
    
    /**
     * Получить все значения из файла конфигурации
     * 
     * @param string $file Имя файла конфигурации
     * @return array
     */
    public static function getFile(string $file): array
    {
        try {
            return self::loadFile($file);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Очистить кэш конфигурации
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
    
    /**
     * Установить значение (только в runtime, не сохраняется в файл)
     * Полезно для тестирования
     * 
     * @param string $key Ключ в формате 'file.group.key'
     * @param mixed $value Значение
     */
    public static function set(string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        
        if (count($parts) < 2) {
            return;
        }
        
        $file = array_shift($parts);
        
        if (!isset(self::$cache[$file])) {
            try {
                self::loadFile($file);
            } catch (\Exception $e) {
                self::$cache[$file] = [];
            }
        }
        
        $config = &self::$cache[$file];
        
        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $config[$part] = $value;
            } else {
                if (!isset($config[$part]) || !is_array($config[$part])) {
                    $config[$part] = [];
                }
                $config = &$config[$part];
            }
        }
    }
}