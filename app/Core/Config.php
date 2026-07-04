<?php

declare(strict_types=1);

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
 *   $config = new Config('/path/to/config');
 *   $config->get('config.app.name')                    // 'w3a'
 *   $config->get('config.app.min_karma_for_downvote')  // 10
 *   $config->get('captcha.config.enabled')             // true
 */
class Config
{
    /**
     * Кэш загруженных конфигурационных файлов
     */
    private array $cache = [];
    
    /**
     * Путь к директории с основной конфигурацией
     */
    private string $configPath;
    
    /**
     * 🔑 Пути к конфигам модулей
     * Формат: ['module_name' => '/path/to/module/Config']
     */
    private array $modulePaths = [];
    
    /**
     * Конструктор с путем к основной конфигурации
     * 
     * @param string $configPath Путь к директории с конфигами
     */
    public function __construct(string $configPath)
    {
        $this->configPath = rtrim($configPath, '/');
        
        if (!is_dir($this->configPath)) {
            throw new \InvalidArgumentException("Config path does not exist: {$configPath}");
        }
    }
    
    /**
     * 🔑 Зарегистрировать путь к конфигам модуля
     * 
     * @param string $moduleName Имя модуля (например, 'captcha', 'auth')
     * @param string $path Абсолютный путь к папке Config модуля
     */
    public function addModulePath(string $moduleName, string $path): void
    {
        $this->modulePaths[strtolower($moduleName)] = rtrim($path, '/');
    }
    
    /**
     * 🔑 Получить все зарегистрированные пути модулей
     * 
     * @return array Массив путей в формате ['module_name' => '/path']
     */
    public function getModulePaths(): array
    {
        return $this->modulePaths;
    }
    
    /**
     * 🔑 Найти путь к файлу конфигурации
     * 
     * @param string $file Имя файла (может содержать префикс модуля: 'module.file')
     * @return string|null Полный путь к файлу или null, если не найден
     */
    private function resolveFilePath(string $file): ?string
    {
        // Проверяем, есть ли префикс модуля (формат: "module_name.file_name")
        $parts = explode('.', $file, 2);
        
        if (count($parts) === 2) {
            $moduleName = strtolower($parts[0]);
            $fileName = $parts[1];
            
            // Ищем в зарегистрированных модулях
            if (isset($this->modulePaths[$moduleName])) {
                $filePath = $this->modulePaths[$moduleName] . '/' . $fileName . '.php';
                if (file_exists($filePath)) {
                    return $filePath;
                }
            }
        }
        
        // Если не нашли в модулях — ищем в основном Config
        $mainPath = $this->configPath . '/' . $file . '.php';
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
    private function loadFile(string $file): array
    {
        // Проверяем кэш
        if (isset($this->cache[$file])) {
            return $this->cache[$file];
        }
        
        $filePath = $this->resolveFilePath($file);
        
        if ($filePath === null) {
            throw new \Exception("Configuration file not found: {$file}");
        }
        
        $config = require $filePath;
        
        if (!is_array($config)) {
            throw new \Exception("Configuration file must return an array: {$file}");
        }
        
        // Кэшируем загруженный файл
        $this->cache[$file] = $config;
        
        return $config;
    }
    
    /**
     * Получить значение из конфигурации
     * 
     * @param string $key Ключ в формате 'file.group.key' или 'module.file.key'
     * @param mixed $default Значение по умолчанию
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        
        if (count($parts) < 2) {
            return $default;
        }
        
        // Определяем имя файла
        $possibleModule = strtolower($parts[0]);
        
        if (isset($this->modulePaths[$possibleModule]) && count($parts) >= 3) {
            // Формат: module.file.key...
            $file = $parts[0] . '.' . $parts[1];
            array_shift($parts);
            array_shift($parts);
        } else {
            // Формат: file.key...
            $file = array_shift($parts);
        }
        
        try {
            $config = $this->loadFile($file);
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
     */
    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }
    
    /**
     * Получить строку из конфигурации
     */
    public function getString(string $key, string $default = ''): string
    {
        return (string) $this->get($key, $default);
    }
    
    /**
     * Получить булево значение из конфигурации
     */
    public function getBool(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }
    
    /**
     * Получить массив из конфигурации
     */
    public function getArray(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);
        return is_array($value) ? $value : $default;
    }
    
    /**
     * Проверить существование ключа
     */
    public function has(string $key): bool
    {
        $sentinel = new \stdClass();
        return $this->get($key, $sentinel) !== $sentinel;
    }
    
    /**
     * Получить все значения из файла конфигурации
     */
    public function getFile(string $file): array
    {
        try {
            return $this->loadFile($file);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Получить всю конфигурацию (все загруженные файлы)
     */
    public function all(): array
    {
        $result = [];
        
        // Загружаем все файлы из основной директории
        $files = glob($this->configPath . '/*.php');
        foreach ($files as $file) {
            $fileName = basename($file, '.php');
            $result[$fileName] = $this->getFile($fileName);
        }
        
        // Загружаем все файлы из модулей
        foreach ($this->modulePaths as $moduleName => $path) {
            $files = glob($path . '/*.php');
            foreach ($files as $file) {
                $fileName = basename($file, '.php');
                $result[$moduleName][$fileName] = $this->getFile("{$moduleName}.{$fileName}");
            }
        }
        
        return $result;
    }
    
    /**
     * Очистить кэш конфигурации
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
    
    /**
     * Установить значение (только в runtime, не сохраняется в файл)
     */
    public function set(string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        
        if (count($parts) < 2) {
            return;
        }
        
        // Определяем имя файла
        $possibleModule = strtolower($parts[0]);
        
        if (isset($this->modulePaths[$possibleModule]) && count($parts) >= 3) {
            $file = $parts[0] . '.' . $parts[1];
            array_shift($parts);
            array_shift($parts);
        } else {
            $file = array_shift($parts);
        }
        
        if (!isset($this->cache[$file])) {
            try {
                $this->loadFile($file);
            } catch (\Exception $e) {
                $this->cache[$file] = [];
            }
        }
        
        $config = &$this->cache[$file];
        
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