<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Резолвер путей к шаблонам с поддержкой тем (Fallback Chain) и кэшированием.
 * 
 * Отвечает за поиск файлов шаблонов (views, layouts, partials) в следующей цепочке приоритетов:
 * 1. themes/{active}/Modules/{module}/Views/{view}.php   ← Переопределение в теме
 * 2. themes/{active}/Views/{view}.php                    ← Глобальное переопределение
 * 3. app/Modules/{module}/Views/{view}.php               ← Оригинальный файл модуля
 * 4. app/Modules/Common/Views/{view}.php                 ← Fallback в общий модуль
 * 
 * Использует двухуровневое кэширование для минимизации обращений к файловой системе:
 * - In-memory кэш (массив $cache) — для повторных вызовов в рамках одного запроса
 * - File-based кэш (storage/cache/views_paths.php) — для повторных запросов между запросами
 * 
 * В development-режиме кэш автоматически инвалидируется при изменении файлов (по mtime).
 * В production-режиме кэш работает постоянно до ручной очистки.
 */
class ViewFinder
{
    private Config $config;
    private string $appPath;
    private string $themesPath;
    
    /** 
     * @var array<string, string> In-memory кэш найденных путей.
     * Ключ: '{moduleName}:{viewName}', Значение: абсолютный путь к файлу.
     * Пример: ['Stories:show' => '/var/www/themes/dark/Modules/Stories/Views/show.php']
     */
    private array $cache = [];
    
    /** 
     * @var string|null Абсолютный путь к файлу кэша путей.
     * Кэш хранится в формате PHP-массива для быстрой загрузки через require.
     */
    private ?string $cacheFile = null;
    
    /** 
     * @var bool Флаг, указывающий, был ли загружен кэш из файла.
     * Предотвращает повторную загрузку кэша в рамках одного запроса.
     */
    private bool $cacheLoaded = false;

    /**
     * Конструктор ViewFinder.
     * 
     * @param Config $config Сервис конфигурации для получения имени активной темы
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        
        // Базовые пути к директориям приложения и тем
        $this->appPath = dirname(__DIR__);
        $this->themesPath = dirname(__DIR__, 2) . '/themes';
        
        // Путь к файлу кэша (создаётся автоматически при первом сохранении)
        $this->cacheFile = dirname(__DIR__, 2) . '/storage/cache/views_paths.php';
    }

    // =========================================================================
    // ПУБЛИЧНЫЕ МЕТОДЫ ПОИСКА ПУТЕЙ
    // =========================================================================

    /**
     * Найти путь к шаблону модуля с учётом активной темы и кэшированием.
     * 
     * Реализует трёхуровневую стратегию поиска:
     * 1. In-memory кэш (мгновенно, если путь уже искался в этом запросе)
     * 2. File-based кэш (быстро, если путь искался в предыдущих запросах)
     * 3. Реальный поиск по файловой системе (медленно, но гарантированно)
     * 
     * @param string $viewName Имя шаблона без расширения (например, 'index', 'show', 'create')
     * @param string $moduleName Имя модуля (например, 'Stories', 'Users', 'Common')
     * @return string Абсолютный путь к найденному файлу шаблона
     * 
     * @throws \RuntimeException Если шаблон не найден ни в теме, ни в модуле
     * 
     * @example
     * $path = $viewFinder->find('show', 'Stories');
     * // Вернёт: '/var/www/themes/dark/Modules/Stories/Views/show.php'
     */
    public function find(string $viewName, string $moduleName): string
    {
        // Формируем уникальный ключ кэша для этой комбинации модуль+шаблон
        $cacheKey = "{$moduleName}:{$viewName}";
        
        // =========================================================================
        // ШАГ 1: Проверяем in-memory кэш (самый быстрый путь)
        // =========================================================================
        
        // Если путь уже был найден в рамках этого запроса — возвращаем его мгновенно
        // Это исключает повторные file_exists() для одних и тех же шаблонов
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        // =========================================================================
        // ШАГ 2: Загружаем file-based кэш (один раз за запрос)
        // =========================================================================
        
        // Если кэш ещё не загружен из файла — делаем это сейчас
        // Это происходит только один раз за весь HTTP-запрос
        if (!$this->cacheLoaded) {
            $this->loadCache();
        }
        
        // =========================================================================
        // ШАГ 3: Проверяем file-based кэш
        // =========================================================================
        
        // Если путь есть в файловом кэше — используем его
        // Это быстрее, чем делать file_exists() для каждого кандидата
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        // =========================================================================
        // ШАГ 4: Кэша нет — выполняем реальный поиск (медленный путь)
        // =========================================================================
        
        // Если кэш не содержит этот путь — ищем его в файловой системе
        // Это происходит только при первом обращении к шаблону или после очистки кэша
        $path = $this->resolvePath($viewName, $moduleName);
        
        // =========================================================================
        // ШАГ 5: Сохраняем найденный путь в кэш
        // =========================================================================
        
        // Сохраняем в in-memory кэш (для повторных вызовов в этом запросе)
        $this->cache[$cacheKey] = $path;
        
        // Сохраняем в file-based кэш (для повторных запросов)
        $this->saveCache();
        
        return $path;
    }

    /**
     * Найти путь к layout-шаблону (каркас страницы).
     * 
     * Layout ищется в следующем порядке:
     * 1. themes/{theme}/layout.php
     * 2. app/Modules/Common/Views/layout.php
     * 
     * @return string Абсолютный путь к файлу layout
     * 
     * @throws \RuntimeException Если layout не найден
     */
    public function findLayout(): string
    {
        // Layout ищем как обычный шаблон модуля Common
        return $this->find('layout', 'Common');
    }

    /**
     * Найти путь к partial-шаблону (фрагмент страницы).
     * 
     * Partials обычно лежат в подпапке 'partials/' внутри Views/.
     * 
     * @param string $partialName Имя partial без расширения (например, '_story_meta', '_avatar')
     * @param string $moduleName Имя модуля
     * @return string Абсолютный путь к файлу partial
     * 
     * @example
     * $path = $viewFinder->findPartial('_story_meta', 'Stories');
     * // Ищет: 'Stories/Views/partials/_story_meta.php'
     */
    public function findPartial(string $partialName, string $moduleName): string
    {
        // Добавляем префикс 'partials/' к имени файла
        return $this->find("partials/{$partialName}", $moduleName);
    }

    /**
     * Проверить существование шаблона без выброса исключения.
     * 
     * Полезно для условного подключения шаблонов:
     * if ($viewFinder->exists('sidebar', 'Common')) { ... }
     * 
     * @param string $viewName Имя шаблона
     * @param string $moduleName Имя модуля
     * @return bool true если шаблон найден, false иначе
     */
    public function exists(string $viewName, string $moduleName): bool
    {
        try {
            // Пытаемся найти шаблон
            $this->find($viewName, $moduleName);
            return true;
        } catch (\RuntimeException) {
            // Если шаблон не найден — возвращаем false
            return false;
        }
    }

    // =========================================================================
    // УПРАВЛЕНИЕ КЭШЕМ
    // =========================================================================

    /**
     * Очистить кэш путей.
     * 
     * Вызывается в следующих случаях:
     * - Смена активной темы
     * - Добавление/удаление шаблонов в теме или модуле
     * - Ручная очистка кэша из админки или CLI
     * 
     * Удаляет как in-memory кэш, так и file-based кэш.
     */
    public function clearCache(): void
    {
        // Очищаем in-memory кэш
        $this->cache = [];
        
        // Помечаем кэш как загруженный (чтобы не загружать удалённый файл)
        $this->cacheLoaded = true;
        
        // Удаляем file-based кэш с диска
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    // =========================================================================
    // ВНУТРЕННИЕ МЕТОДЫ ПОИКА И КЭШИРОВАНИЯ
    // =========================================================================

    /**
     * Основная логика поиска пути к шаблону (без кэша).
     * 
     * Выполняет реальный поиск по файловой системе, проверяя кандидатов в порядке приоритета.
     * Используется только если кэш не содержит нужный путь.
     * 
     * @param string $viewName Имя шаблона
     * @param string $moduleName Имя модуля
     * @return string Абсолютный путь к найденному файлу
     * 
     * @throws \RuntimeException Если ни один кандидат не существует
     */
    private function resolvePath(string $viewName, string $moduleName): string
    {
        // Получаем имя активной темы из конфигурации
        $theme = $this->getActiveTheme();
        
        // Формируем список кандидатов в порядке приоритета
        // Система проверит каждый путь через file_exists() и вернёт первый существующий
        $candidates = [
            // Приоритет 1: Переопределение в теме для конкретного модуля
            // Пример: themes/dark/Modules/Stories/Views/show.php
            "{$this->themesPath}/{$theme}/Modules/{$moduleName}/Views/{$viewName}.php",
            
            // Приоритет 2: Глобальное переопределение в теме
            // Пример: themes/dark/Views/show.php
            // Используется для общих шаблонов, не привязанных к модулю
            "{$this->themesPath}/{$theme}/Views/{$viewName}.php",
            
            // Приоритет 3: Оригинальный файл модуля
            // Пример: app/Modules/Stories/Views/show.php
            // Это стандартный путь, если тема не переопределяет шаблон
            "{$this->appPath}/Modules/{$moduleName}/Views/{$viewName}.php",
            
            // Приоритет 4: Fallback в модуль Common
            // Пример: app/Modules/Common/Views/show.php
            // Используется для общих компонентов (layout, header, footer)
            "{$this->appPath}/Modules/Common/Views/{$viewName}.php",
        ];

        // Проверяем каждого кандидата по порядку
        foreach ($candidates as $path) {
            if (file_exists($path)) {
                // Нашли первый существующий файл — возвращаем его
                return $path;
            }
        }

        // Если ни один кандидат не существует — выбрасываем исключение
        // Сообщение включает все проверенные пути для упрощения отладки
        throw new \RuntimeException(
            "View '{$viewName}' not found for module '{$moduleName}' (theme: '{$theme}'). " .
            "Searched in: " . implode(', ', $candidates)
        );
    }

    /**
     * Загрузить кэш путей из файла.
     * 
     * Выполняется один раз за HTTP-запрос (при первом вызове find()).
     * В development-режиме проверяет актуальность кэша по mtime.
     */
    private function loadCache(): void
    {
        // Помечаем кэш как загруженный (чтобы не загружать повторно)
        $this->cacheLoaded = true;
        
        // Если файл кэша не существует — выходим
        if (!file_exists($this->cacheFile)) {
            return;
        }
        
        // =========================================================================
        // Проверка актуальности кэша в development-режиме
        // =========================================================================
        
        // В development проверяем, не изменились ли модули или тема после создания кэша
        // Это позволяет автоматически обновлять кэш при разработке без ручной очистки
        if ($this->isDevelopment()) {
            $cacheMtime = filemtime($this->cacheFile);
            
            // Проверяем mtime директории модулей
            $modulesMtime = file_exists($this->appPath . '/Modules') 
                ? filemtime($this->appPath . '/Modules') 
                : 0;
            
            // Проверяем mtime директории активной темы
            $themePath = $this->themesPath . '/' . $this->getActiveTheme();
            $themeMtime = file_exists($themePath) ? filemtime($themePath) : 0;
            
            // Если модули или тема изменились после создания кэша — сбрасываем кэш
            if ($modulesMtime > $cacheMtime || $themeMtime > $cacheMtime) {
                $this->clearCache();
                return;
            }
        }
        
        // =========================================================================
        // Загрузка кэша из файла
        // =========================================================================
        
        try {
            // Загружаем PHP-массив из файла через require
            // Формат файла: return ['paths' => [...], 'generated_at' => '...'];
            $data = require $this->cacheFile;
            
            // Проверяем корректность структуры данных
            if (is_array($data) && isset($data['paths'])) {
                $this->cache = $data['paths'];
            }
        } catch (\Throwable $e) {
            // Если файл кэша повреждён или недоступен — игнорируем ошибку
            // и начинаем с пустого кэша (это безопаснее, чем падать с ошибкой)
            $this->cache = [];
        }
    }

    /**
     * Сохранить кэш путей в файл (атомарно).
     * 
     * Использует атомарную запись (write to temp file + rename) для защиты от race condition.
     * Также сбрасывает OPcache для этого файла, чтобы PHP загрузил новую версию.
     */
    private function saveCache(): void
    {
        // Создаём директорию для кэша, если она не существует
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Формируем данные для сохранения
        $data = [
            'generated_at' => date('Y-m-d H:i:s'),
            'theme' => $this->getActiveTheme(),
            'paths' => $this->cache,
        ];
        
        // Генерируем PHP-код для сохранения
        // var_export() создаёт валидный PHP-массив, который можно загрузить через require
        $code = "<?php\nreturn " . var_export($data, true) . ";\n";
        
        // =========================================================================
        // Атомарная запись (защита от race condition)
        // =========================================================================
        
        // 1. Записываем во временный файл
        $tmp = $this->cacheFile . '.tmp.' . getmypid();
        file_put_contents($tmp, $code, LOCK_EX);
        
        // 2. Атомарно переименовываем временный файл в целевой
        // Это гарантирует, что другие процессы никогда не увидят частично записанный файл
        rename($tmp, $this->cacheFile);
        
        // 3. Сбрасываем OPcache для этого файла
        // Это нужно, чтобы PHP загрузил новую версию кэша, а не старую из OPcache
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->cacheFile, true);
        }
    }

    // =========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    /**
     * Получить имя активной темы из конфигурации.
     * 
     * @return string Имя папки темы (например, 'default', 'dark_mode')
     */
    public function getActiveTheme(): string
    {
        return $this->config->get('config.app.theme', 'default');
    }

    /**
     * Получить абсолютный путь к директории активной темы.
     * 
     * @return string Абсолютный путь (например, '/var/www/themes/dark_mode')
     */
    public function getActiveThemePath(): string
    {
        return $this->themesPath . '/' . $this->getActiveTheme();
    }

    /**
     * Проверить, находится ли приложение в режиме разработки.
     * 
     * Используется для определения, нужно ли автоматически инвалидировать кэш.
     * 
     * @return bool true если режим development, false если production
     */
    private function isDevelopment(): bool
    {
        return $this->config->get('config.app.env', 'development') === 'development';
    }
}