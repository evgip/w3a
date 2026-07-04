<?php

namespace App\Core;

use App\Core\Events\EventDispatcher;

class Application
{
    private Container $container;
    private Request $request;
    private array $config;

    public function __construct()
    {
        $this->config = require dirname(__DIR__) . '/Config/config.php';
        $this->setupErrorHandling();
    }

    public function bootstrap(): self
    {
        Benchmark::start();
        Lang::init();

        // 1. Создаём контейнер
        $this->container = new Container();
        $this->container->instance(Container::class, $this->container);
        
        // 2. Создаём Request
        $this->request = new Request();
        $this->container->singleton(Request::class, fn() => $this->request);

        // 3. Создаём EventDispatcher
        $eventDispatcher = new EventDispatcher();
        $this->container->singleton(EventDispatcher::class, fn() => $eventDispatcher);

        // 4. Регистрируем провайдеры (включая Database, Session, Logger, IpResolver, Security)
        $this->registerProviders();

        // 5. Вызываем Security через контейнер
        $this->sendSecurityHeaders();

        // 6. Вызываем Firewall — контейнер уже готов!
        $this->checkFirewall();

        return $this;
    }

    /**
     * Отправка заголовков безопасности через контейнер
     */
    private function sendSecurityHeaders(): void
    {
        try {
            $security = $this->container->get(Security::class);
            $security->sendCspHeader();
        } catch (\Throwable $e) {
            // Fallback: если Security не зарегистрирован, пропускаем
            error_log("Security headers skipped: " . $e->getMessage());
        }
    }

    /**
     * Проверка Firewall через контейнер
     */
    private function checkFirewall(): void
    {
        $database = $this->container->get(Database::class);
        $ipResolver = $this->container->get(IpResolver::class);
        
        $firewall = new Firewall($database, $this->container, $ipResolver);
        $firewall->check();
    }

    /**
     * Регистрация провайдеров модулей
     */
    private function registerProviders(): void
    {
        // 1. Регистрируем провайдер ядра
        $coreProvider = new ModuleServiceProvider($this->request);
        $coreProvider->register($this->container);

        // 2. Автоматически загружаем провайдеры модулей
        $modulesPath = dirname(__DIR__) . '/Modules';
        
        if (!is_dir($modulesPath)) {
            return;
        }

        $modules = array_diff(scandir($modulesPath), ['.', '..']);
        $providers = [];

        // Сначала регистрируем все провайдеры
        foreach ($modules as $module) {
            $providerClass = "App\\Modules\\{$module}\\ModuleServiceProvider";
            
            if (class_exists($providerClass)) {
                $configPath = $modulesPath . '/' . $module . '/Config';
                if (is_dir($configPath)) {
                    Config::addModulePath(strtolower($module), $configPath);
                }

                $provider = new $providerClass();
                $provider->register($this->container);
                $providers[] = $provider;
            }
        }

        // Потом вызываем boot() для всех провайдеров
        foreach ($providers as $provider) {
            if (method_exists($provider, 'boot')) {
                $provider->boot();
            }
        }
    }

    public function run(): void
    {
        try {
            $router = new Router($this->request, $this->container);
            $router->dispatch();
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    private function setupErrorHandling(): void
    {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
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
            // Fallback: логируем напрямую
            $logFile = dirname(__DIR__, 2) . '/storage/logs/app.log';
            $logger = new Logger($logFile);
            $logger->error($errorMessage, [
                'trace' => $e->getTraceAsString(),
                'url' => $_SERVER['REQUEST_URI'] ?? '/'
            ]);
        }

        $isDevelopment = ($this->config['app']['env'] ?? 'development') === 'development';
        
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
            $controller->notFound("Извините, на сервере произошла внутренняя ошибка. Инженеры уже уведомлены.");
            exit;
        }
        echo "<h1>500 Internal Server Error</h1><p>Извините, на сервере произошла непредвиденная ошибка.</p>";
    }
}