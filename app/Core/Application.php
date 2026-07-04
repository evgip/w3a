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

    private function registerProviders(): void
    {
        $coreProvider = new ModuleServiceProvider($this->request);
        $coreProvider->register($this->container);

        $modulesPath = dirname(__DIR__) . '/Modules';
        
        if (!is_dir($modulesPath)) {
            return;
        }

        $modules = array_diff(scandir($modulesPath), ['.', '..']);
        $providers = [];

        foreach ($modules as $module) {
            $providerClass = "App\\Modules\\{$module}\\ModuleServiceProvider";
            
            if (class_exists($providerClass)) {
                $configPath = $modulesPath . '/' . $module . '/Config';
                if (is_dir($configPath)) {
                    $this->config->addModulePath(strtolower($module), $configPath);
                }

                $provider = new $providerClass();
                $provider->register($this->container);
                $providers[] = $provider;
            }
        }

        foreach ($providers as $provider) {
            if (method_exists($provider, 'boot')) {
                $provider->boot();
            }
        }
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
        http_response_code($e->getStatusCode());
        header('Location: ' . $e->getUrl());
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
                
                match($e->getStatusCode()) {
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