<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Events\EventDispatcher;
use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\JsonResponseException;
use App\Core\Exceptions\RedirectException;
use App\Core\Exceptions\CsrfException;
use App\Core\Logger; 

class Application
{
    private Container $container;
    private Request $request;
    private Config $config;

    /**
     * Путь к кэшу списка провайдеров
     */
    private function getProvidersCachePath(): string
    {
        return dirname(__DIR__, 2) . '/storage/cache/providers.php';
    }

    /**
     * Путь к директории модулей
     */
    private function getModulesPath(): string
    {
        return dirname(__DIR__) . '/Modules';
    }

    public function bootstrap(): self
    {
        Benchmark::start();
        Lang::init();

        $this->container = new Container();
        $this->container->instance(Container::class, $this->container);

        $configPath = dirname(__DIR__) . '/Config';
        $this->config = new Config($configPath);
        $this->container->instance(Config::class, $this->config);

        $this->setupErrorHandling();

        $GLOBALS['app_container'] = $this->container;

        $this->request = new Request();
        $this->container->singleton(Request::class, fn() => $this->request);

        // 1. Создаем экземпляр логгера и регистрируем его в контейнере
        // (Это также исправляет работу метода logError() внизу файла)
        $logger = new Logger();
        $this->container->singleton(Logger::class, fn() => $logger);

        // 2. Передаем логгер в диспетчер событий
        $eventDispatcher = new EventDispatcher($logger);
        $this->container->singleton(EventDispatcher::class, fn() => $eventDispatcher);

        $this->registerProviders();
        $this->sendSecurityHeaders();
        $this->checkFirewall();

        return $this;
    }

    private function sendSecurityHeaders(): void
    {
        try {
            $security = $this->container->get(Security::class);
            $security->sendCspHeader();
        } catch (\Throwable $e) {
            error_log("Security headers skipped: " . $e->getMessage());
        }
    }

    private function checkFirewall(): void
    {
        $database = $this->container->get(Database::class);
        $ipResolver = $this->container->get(IpResolver::class);

        $firewall = new Firewall($database, $this->container, $ipResolver);
        $firewall->check();
    }

    /**
     * Регистрация провайдеров модулей с кэшированием списка.
     *
     * Логика:
     *  - В production: всегда используем кэш (если есть)
     *  - В development: проверяем актуальность кэша по mtime
     *  - Если кэш устарел или отсутствует — пересобираем
     */
    private function registerProviders(): void
    {
        // 1. Ядро — всегда регистрируется отдельно (не кэшируется)
        $coreProvider = new ModuleServiceProvider($this->request);
        $coreProvider->register($this->container);

        // 2. Получаем список модульных провайдеров (из кэша или сканированием)
        $moduleProvidersData = $this->getModuleProvidersData();

        // 3. Регистрируем модульные провайдеры и собираем для boot
        $providers = [];
        foreach ($moduleProvidersData as $module => $data) {
            $providerClass = $data['class'];
            $configPath = $data['config_path'] ?? null;

            // Подключаем конфиг модуля (как было)
            if ($configPath !== null && is_dir($configPath)) {
                $this->config->addModulePath(strtolower($module), $configPath);
            }

            $provider = new $providerClass();
            $provider->register($this->container);
            $providers[] = $provider;
        }

        // 4. Boot phase
        foreach ($providers as $provider) {
            if (method_exists($provider, 'boot')) {
                $provider->boot();
            }
        }
    }

    /**
     * Получение списка провайдеров модулей с кэшированием.
     *
     * @return array<string, array{class: string, config_path: string|null}>
     */
    private function getModuleProvidersData(): array
    {
        $modulesPath = $this->getModulesPath();
        $cacheFile = $this->getProvidersCachePath();

        if (!is_dir($modulesPath)) {
            return [];
        }

        $env = $this->config->get('config.app.env', 'development');

        // Production: всегда используем кэш, если он есть
        if ($env === 'production' && file_exists($cacheFile)) {
            $cache = @include $cacheFile;
            if (is_array($cache) && isset($cache['providers'])) {
                return $cache['providers'];
            }
        }

        // Development: проверяем актуальность кэша
        if (file_exists($cacheFile) && !$this->isProvidersCacheStale($cacheFile, $modulesPath)) {
            $cache = @include $cacheFile;
            if (is_array($cache) && isset($cache['providers'])) {
                return $cache['providers'];
            }
        }

        // Кэш отсутствует или устарел — пересобираем
        return $this->rebuildProvidersCache($cacheFile, $modulesPath);
    }

    /**
     * Проверка актуальности кэша провайдеров.
     * Кэш считается устаревшим, если:
     *  - Изменилась директория Modules (добавлен/удалён модуль)
     *  - Изменился любой ModuleServiceProvider.php
     *  - Изменилась директория Config любого модуля
     */
    private function isProvidersCacheStale(string $cacheFile, string $modulesPath): bool
    {
        $cache = @include $cacheFile;
        if (!is_array($cache) || !isset($cache['cache_time'])) {
            return true;
        }

        $cacheTime = $cache['cache_time'];

        // Проверяем mtime директории Modules
        if (filemtime($modulesPath) > $cacheTime) {
            return true;
        }

        // Проверяем каждый модуль
        $modules = array_diff(scandir($modulesPath), ['.', '..']);
        foreach ($modules as $module) {
            $modulePath = $modulesPath . '/' . $module;
            if (!is_dir($modulePath)) {
                continue;
            }

            // Проверяем ModuleServiceProvider.php
            $providerFile = $modulePath . '/ModuleServiceProvider.php';
            if (file_exists($providerFile) && filemtime($providerFile) > $cacheTime) {
                return true;
            }

            // Проверяем директорию Config модуля
            $configPath = $modulePath . '/Config';
            if (is_dir($configPath) && filemtime($configPath) > $cacheTime) {
                return true;
            }
        }

        return false;
    }

    /**
     * Пересборка кэша провайдеров.
     *
     * @return array<string, array{class: string, config_path: string|null}>
     */
    private function rebuildProvidersCache(string $cacheFile, string $modulesPath): array
    {
        $providers = [];
        $modules = array_diff(scandir($modulesPath), ['.', '..']);

        foreach ($modules as $module) {
            $providerClass = "App\\Modules\\{$module}\\ModuleServiceProvider";

            if (class_exists($providerClass)) {
                $configPath = $modulesPath . '/' . $module . '/Config';
                $providers[$module] = [
                    'class' => $providerClass,
                    'config_path' => is_dir($configPath) ? $configPath : null,
                ];
            }
        }

        // Формируем данные для кэша
        $cacheData = [
            'providers' => $providers,
            'cache_time' => time(),
            'generated_at' => date('Y-m-d H:i:s'),
            'modules_mtime' => filemtime($modulesPath),
        ];

        // Атомарная запись
        $this->writeCacheAtomic($cacheFile, $cacheData);

        return $providers;
    }

    /**
     * Атомарная запись кэша (защита от race condition).
     */
    private function writeCacheAtomic(string $file, array $data): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new \RuntimeException("Failed to create cache directory: {$dir}");
            }
        }

        $code = "<?php\n";
        $code .= "// Auto-generated at {$data['generated_at']}\n";
        $code .= "// DO NOT EDIT - regenerated automatically\n\n";
        $code .= "return " . var_export($data, true) . ";\n";

        // Сначала пишем во временный файл
        $tmp = $file . '.tmp.' . getmypid();
        if (file_put_contents($tmp, $code, LOCK_EX) === false) {
            throw new \RuntimeException("Failed to write cache file: {$file}");
        }

        // Атомарное переименование
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            throw new \RuntimeException("Failed to rename cache file: {$tmp} -> {$file}");
        }

        // Сбрасываем opcache для этого файла
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
    }

    /**
     * Принудительная пересборка кэша провайдеров.
     * Используется из CLI или админки.
     *
     * @return array{success: bool, providers_count: int, cache_file: string, providers: array}
     */
    public function rebuildProvidersCacheManual(): array
    {
        $modulesPath = $this->getModulesPath();
        $cacheFile = $this->getProvidersCachePath();

        if (!is_dir($modulesPath)) {
            return [
                'success' => false,
                'providers_count' => 0,
                'cache_file' => $cacheFile,
                'providers' => [],
                'error' => 'Modules directory not found',
            ];
        }

        $providers = $this->rebuildProvidersCache($cacheFile, $modulesPath);

        return [
            'success' => true,
            'providers_count' => count($providers),
            'cache_file' => $cacheFile,
            'providers' => array_keys($providers),
        ];
    }

    public function run(): void
    {
        try {
            $router = new Router($this->request, $this->container, $this->config);
            $router->dispatch();
        } catch (RedirectException $e) {
            // Обработка редиректов БЕЗ логирования
            $this->handleRedirect($e);
        } catch (JsonResponseException $e) {
            $this->handleJsonResponse($e);
        } catch (CsrfException $e) {
            $this->handleCsrfException($e);
        } catch (HttpException $e) {
            $this->handleHttpException($e);
        } catch (\Throwable $e) {
            // Только реальные ошибки попадают сюда
            $this->handleException($e);
        }
    }

    /**
     * Обработка редиректов (без логирования!)
     */
    private function handleRedirect(RedirectException $e): void
    {
        http_response_code($e->statusCode);
        header('Location: ' . $e->url);
        exit;
    }

    /**
     * Обработка JSON ответов
     */
    private function handleJsonResponse(JsonResponseException $e): void
    {
        http_response_code($e->getStatusCode());
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($e->getData(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Обработка CSRF исключений
     * 
     * Для AJAX — JSON ответ
     * Для обычных запросов — делегируем ErrorsController
     */
    private function handleCsrfException(CsrfException $e): void
    {
        http_response_code(419);
        
        $context = $e->getContext();
        $isAjax = $context['is_ajax'] ?? false;
        
        // Логируем попытку CSRF атаки
        $this->logError('warning', 'CSRF validation failed', [
            'url' => $context['url'] ?? $this->request->getUri(),
            'method' => $context['method'] ?? $this->request->getMethod(),
            'ip' => $context['ip'] ?? $this->request->getIp(),
            'is_ajax' => $isAjax,
        ]);
        
        // Для AJAX возвращаем JSON (это API ответ, не страница)
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => 'CSRF token validation failed',
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // Для обычных запросов — ТОЛЬКО контроллер ошибок
        $this->renderErrorPage('csrf', $e->getMessage());
    }

    /**
     * Обработка HTTP исключений
     */
    private function handleHttpException(HttpException $e): void
    {
        http_response_code($e->getStatusCode());

        // Логируем ошибки 4xx и 5xx
        $logLevel = $e->getStatusCode() >= 500 ? 'error' : 'warning';
        $this->logError($logLevel, $e->getMessage(), [
            'status' => $e->getStatusCode(),
            'url' => $this->request->getUri(),
            'method' => $this->request->getMethod(),
            'ip' => $this->request->getIp(),
        ]);

        // ТОЛЬКО контроллер ошибок — никакого дублирующего HTML
        $method = match ($e->getStatusCode()) {
            400 => 'badRequest',
            403 => 'forbidden',
            404 => 'notFound',
            419 => 'csrf',
            default => 'show',
        };
        
        $this->renderErrorPage($method, $e->getMessage(), $e->getStatusCode());
    }

    /**
     * Настройка обработки ошибок с учетом окружения
     * 
     * В production:
     *  - display_errors = 0 (не показываем ошибки пользователю)
     *  - log_errors = 1 (логируем в файл)
     * 
     * В development:
     *  - display_errors = 1 (показываем ошибки для отладки)
     */
    private function setupErrorHandling(): void
    {
        $env = $this->config->get('config.app.env', 'development');
        $isProduction = ($env === 'production');

        // Включаем отчет обо всех ошибках
        error_reporting(E_ALL);

        if ($isProduction) {
            // Production: НЕ показываем ошибки, но логируем
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
            ini_set('log_errors', '1');
            
            // Путь к логам PHP ошибок
            $logPath = dirname(__DIR__, 2) . '/storage/logs/php_errors.log';
            ini_set('error_log', $logPath);
        } else {
            // Development: показываем ошибки для отладки
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
        }
    }

    private function handleException(\Throwable $e): void
    {
        $errorMessage = $e->getMessage() . " в файле " . $e->getFile() . " на строке " . $e->getLine();

        $this->logError('error', $errorMessage, [
            'trace' => $e->getTraceAsString(),
            'url' => $this->request->getUri(),
            'method' => $this->request->getMethod(),
            'ip' => $this->request->getIp(),
        ]);

        $isDevelopment = $this->config->get('config.app.env', 'development') === 'development';

        http_response_code(500);

        if ($isDevelopment) {
            $this->showDevelopmentError($e);
        } else {
            // ТОЛЬКО контроллер ошибок
            $this->renderErrorPage('serverError', "Извините, на сервере произошла внутренняя ошибка. Инженеры уже уведомлены.");
        }
    }

    private function showDevelopmentError(\Throwable $e): void
    {
        echo '<div style="background: #f8d7da; color: #721c24; padding: 20px; font-family: monospace; margin: 20px; border: 1px solid #f5c6cb;">';
        echo '<h2>💥 Ошибка разработки (Development Mode):</h2>';
        echo '<strong>Сообщение:</strong> ' . htmlspecialchars($e->getMessage()) . '<br><br>';
        echo '<strong>Файл:</strong> ' . htmlspecialchars($e->getFile()) . ' (строка ' . $e->getLine() . ')<br><br>';
        echo '<strong>Стек вызовов записан в storage/logs/app.log</strong>';
        echo '</div>';
    }

    // =========================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // =========================================================================

    /**
     * Рендер страницы ошибки через ErrorsController
     * 
     * Если контроллер недоступен — логируем критическую ошибку
     */
    private function renderErrorPage(string $method, string $message, int $code = 500): void
    {
        $errorControllerClass = "App\\Modules\\Errors\\Controllers\\ErrorsController";
        
        if (!class_exists($errorControllerClass)) {
            $this->logError('critical', "ErrorsController not found", [
                'method' => $method,
                'message' => $message,
            ]);
            return;
        }
        
        try {
            $controller = $this->container->make($errorControllerClass);
            
            if ($method === 'show') {
                $controller->show($code, $message);
            } else {
                $controller->$method($message);
            }
        } catch (\Throwable $controllerError) {
            // Если контроллер упал — логируем, но не показываем HTML
            $this->logError('critical', "ErrorsController failed to render", [
                'original_method' => $method,
                'original_message' => $message,
                'controller_error' => $controllerError->getMessage(),
            ]);
        }
    }

    /**
     * Безопасное логирование ошибок
     */
    private function logError(string $level, string $message, array $context = []): void
    {
        try {
            $logger = $this->container->get(Logger::class);
            $logger->$level($message, $context);
        } catch (\Throwable $logError) {
            // Если логгер недоступен — используем error_log как последний шанс
            error_log("[{$level}] {$message} " . json_encode($context));
        }
    }
}