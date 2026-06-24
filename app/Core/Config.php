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
 * - 🔑 Конфигурационные файлы модулей
 * 
 * Примеры использования:
 *   // Основной конфиг (app/Config/config.php)
 *   Config::get('config.app.name')                    // 'w3a'
 *   Config::get('config.app.min_karma_for_downvote')  // 10
 *   Config::get('constants.pagination.stories_per_page') // 15
 *   Config::get('database.host', 'localhost')         // 'localhost'
 *   
 *   // 🔑 Конфиг модуля (app/Modules/Captcha/Config/captcha.php)
 *   Config::get('captcha.config.enabled')             // true
 *   Config::get('captcha.config.driver')              // 'yandex'
 *   Config::get('captcha.config.yandex.site_key')     // 'ysc1_...'
 */
class Config
{
    /**
     * Кэш загруженных конфигурационных файлов
     */
    private static array $cache = [];
    
    /**
     * Путь к директории с основной конфигурацией
     */
    private static string $configPath = '';
    
    /**
     * 🔑 Пути к конфигам модулей
     * Формат: ['module_name' => '/path/to/module/Config']
     */
    private static array $modulePaths = [];
    
    /**
     * Инициализация пути к основной конфигурации
     */
    private static function initPath(): void
    {
        if (empty(self::$configPath)) {
            self::$configPath = dirname(__DIR__) . '/Config';
        }
    }
    
    /**
     * 🔑 Зарегистрировать путь к конфигам модуля
     * 
     * Вызывается в ModuleServiceProvider каждого модуля для регистрации
     * своего пути к конфигурации.
     * 
     * @param string $moduleName Имя модуля (например, 'captcha', 'auth')
     * @param string $path Абсолютный путь к папке Config модуля
     */
    public static function addModulePath(string $moduleName, string $path): void
    {
        self::$modulePaths[strtolower($moduleName)] = rtrim($path, '/');
    }
    
    /**
     * 🔑 Получить все зарегистрированные пути модулей
     * 
     * @return array Массив путей в формате ['module_name' => '/path']
     */
    public static function getModulePaths(): array
    {
        return self::$modulePaths;
    }
    
    /**
     * 🔑 Найти путь к файлу конфигурации
     * 
     * Сначала ищет в зарегистрированных модулях, затем в основном Config.
     * 
     * Формат имени файла:
     * - 'captcha.config' → ищет в app/Modules/Captcha/Config/captcha.php
     * - 'config'         → ищет в app/Config/config.php
     * 
     * @param string $file Имя файла (может содержать префикс модуля: 'module.file')
     * @return string|null Полный путь к файлу или null, если не найден
     */
    private static function resolveFilePath(string $file): ?string
    {
        self::initPath();
        
        // Проверяем, есть ли префикс модуля (формат: "module_name.file_name")
        $parts = explode('.', $file, 2);
        
        if (count($parts) === 2) {
            $moduleName = strtolower($parts[0]);
            $fileName = $parts[1];
            
            // Ищем в зарегистрированных модулях
            if (isset(self::$modulePaths[$moduleName])) {
                $filePath = self::$modulePaths[$moduleName] . '/' . $fileName . '.php';
                if (file_exists($filePath)) {
                    return $filePath;
                }
            }
        }
        
        // Если не нашли в модулях — ищем в основном Config
        // (обратная совместимость: 'config' ищет app/Config/config.php)
        $mainPath = self::$configPath . '/' . $file . '.php';
        if (file_exists($mainPath)) {
            return $mainPath;
        }
        
        return null;
    }
    
    /**
     * Загрузить конфигурационный файл
     * 
     * @param string $file Имя файла (может быть 'module.file' или просто 'file')
     * @return array Загруженная конфигурация
     * @throws \Exception Если файл не найден
     */
    private static function loadFile(string $file): array
    {
        // Проверяем кэш (используем полное имя файла как ключ)
        if (isset(self::$cache[$file])) {
            return self::$cache[$file];
        }
        
        $filePath = self::resolveFilePath($file);
        
        if ($filePath === null) {
            throw new \Exception("Configuration file not found: {$file}");
        }
        
        $config = require $filePath;
        
        if (!is_array($config)) {
            throw new \Exception("Configuration file must return an array: {$file}");
        }
        
        // Кэшируем загруженный файл
        self::$cache[$file] = $config;
        
        return $config;
    }
    
    /**
     * Получить значение из конфигурации
     * 
     * @param string $key Ключ в формате 'file.group.key' или 'module.file.key'
     * @param mixed $default Значение по умолчанию
     * @return mixed
     * 
     * Примеры:
     *   // Основной конфиг (app/Config/config.php)
     *   Config::get('config.app.name')
     *   Config::get('config.app.min_karma_for_downvote', 10)
     *   Config::get('constants.pagination.stories_per_page')
     *   Config::get('database.host', 'localhost')
     *   
     *   // 🔑 Конфиг модуля (app/Modules/Captcha/Config/captcha.php)
     *   Config::get('captcha.config.enabled')
     *   Config::get('captcha.config.driver')
     *   Config::get('captcha.config.yandex.site_key')
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        
        if (count($parts) < 2) {
            return $default;
        }
        
        // 🔑 Определяем имя файла:
        // Если первый сегмент — это зарегистрированный модуль,
        // то файл = "module.second_segment"
        // Иначе файл = первый сегмент (старый формат)
        $possibleModule = strtolower($parts[0]);
        
        if (isset(self::$modulePaths[$possibleModule]) && count($parts) >= 3) {
            // Формат: module.file.key...
            $file = $parts[0] . '.' . $parts[1];
            array_shift($parts); // убираем module
            array_shift($parts); // убираем file
        } else {
            // Формат: file.key... (старый формат, обратная совместимость)
            $file = array_shift($parts);
        }
        
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
     * @param string $file Имя файла (может быть 'module.file' или просто 'file')
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
     * @param string $key Ключ в формате 'file.group.key' или 'module.file.key'
     * @param mixed $value Значение
     */
    public static function set(string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        
        if (count($parts) < 2) {
            return;
        }
        
        // 🔑 Определяем имя файла (аналогично методу get)
        $possibleModule = strtolower($parts[0]);
        
        if (isset(self::$modulePaths[$possibleModule]) && count($parts) >= 3) {
            $file = $parts[0] . '.' . $parts[1];
            array_shift($parts);
            array_shift($parts);
        } else {
            $file = array_shift($parts);
        }
        
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