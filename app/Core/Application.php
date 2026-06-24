<?php

namespace App\Core;

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
        Security::sendCspHeader();
        Firewall::check();

        $this->container = new Container();
        $this->request = new Request();
        $this->container->singleton(Request::class, fn() => $this->request);

        // 🔑 Регистрируем провайдеры модулей
        $this->registerProviders();

        return $this;
    }

    /**
     * 🔑 НОВЫЙ МЕТОД: Регистрация провайдеров модулей
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

        foreach ($modules as $module) {
            $providerClass = "App\\Modules\\{$module}\\ModuleServiceProvider";
            
            if (class_exists($providerClass)) {
                // 🔑 Автоматически регистрируем путь к конфигам модуля
                $configPath = $modulesPath . '/' . $module . '/Config';
                if (is_dir($configPath)) {
                    Config::addModulePath(strtolower($module), $configPath);
                }

                // Регистрируем провайдер
                $provider = new $providerClass();
                $provider->register($this->container);
                
                // Вызываем boot(), если он есть
                if (method_exists($provider, 'boot')) {
                    $provider->boot();
                }
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
        Logger::error($errorMessage, [
            'trace' => $e->getTraceAsString(),
            'url' => $_SERVER['REQUEST_URI'] ?? '/'
        ]);

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
            (new $errorController())->notFound("Извините, на сервере произошла внутренняя ошибка. Инженеры уже уведомлены.");
            exit;
        }
        echo "<h1>500 Internal Server Error</h1><p>Извините, на сервере произошла непредвиденная ошибка.</p>";
    }
}