<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Events\EventDispatcher;
use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\JsonResponseException;
use App\Core\Exceptions\RedirectException;

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

    public function __construct()
    {
        $this->setupErrorHandling();
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

        $GLOBALS['app_container'] = $this->container;

        $this->request = new Request();
        $this->container->singleton(Request::class, fn() => $this->request);

        $eventDispatcher = new EventDispatcher();
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
     * Обработка HTTP исключений
     */
    private function handleHttpException(HttpException $e): void
    {
        http_response_code($e->getStatusCode());

        // Логируем ошибки 5xx
        if ($e->getStatusCode() >= 500) {
            try {
                $logger = $this->container->get(Logger::class);
                $logger->error($e->getMessage(), [
                    'status' => $e->getStatusCode(),
                    'url' => $this->request->getUri(),
                ]);
            } catch (\Throwable $logError) {
                // Игнорируем ошибки логирования
            }
        }

        $errorController = "App\\Modules\\Errors\\Controllers\\ErrorsController";

        if (class_exists($errorController)) {
            try {
                $controller = $this->container->make($errorController);

                match ($e->getStatusCode()) {
                    400 => $controller->badRequest($e->getMessage()),
                    403 => $controller->forbidden($e->getMessage()),
                    404 => $controller->notFound($e->getMessage()),
                    419 => $controller->csrf($e->getMessage()),
                    default => $controller->show($e->getStatusCode(), $e->getMessage()),
                };
                return;
            } catch (\Throwable $controllerError) {
                // Fallback если контроллер упал
            }
        }

        echo "<h1>Error {$e->getStatusCode()}</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>";
    }

    private function setupErrorHandling(): void
    {
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);
    }

    private function handleException(\Throwable $e): void
    {
        $errorMessage = $e->getMessage() . " в файле " . $e->getFile() . " на строке " . $e->getLine();

        try {
            $logger = $this->container->get(Logger::class);
            $logger->error($errorMessage, [
                'trace' => $e->getTraceAsString(),
                'url' => $_SERVER['REQUEST_URI'] ?? '/'
            ]);
        } catch (\Throwable $logError) {
            $logFile = dirname(__DIR__, 2) . '/storage/logs/app.log';
            $logger = new Logger($logFile);
            $logger->error($errorMessage, [
                'trace' => $e->getTraceAsString(),
                'url' => $_SERVER['REQUEST_URI'] ?? '/'
            ]);
        }

        $isDevelopment = $this->config->get('config.app.env', 'development') === 'development';

        http_response_code(500);

        if ($isDevelopment) {
            $this->showDevelopmentError($e);
        } else {
            $this->showProductionError();
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

    private function showProductionError(): void
    {
        $errorController = "App\\Modules\\Errors\\Controllers\\ErrorsController";
        if (class_exists($errorController)) {
            $controller = $this->container->make($errorController);
            $controller->serverError("Извините, на сервере произошла внутренняя ошибка. Инженеры уже уведомлены.");
            exit;
        }
        echo "<h1>500 Internal Server Error</h1><p>Извините, на сервере произошла непредвиденная ошибка.</p>";
    }
}