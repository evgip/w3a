<?php

namespace App\Core;

class Router
{
    protected array $routes = [];
    protected array $namedRoutes = [];
    protected Request $request;
    protected string $cacheFile;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->cacheFile = dirname(__DIR__, 2) . '/storage/cache/routes_compiled.php';
        $this->loadRoutes();
    }

    /**
     * Умная загрузка маршрутов: Кэш (Production) vs Сканирование (Development)
     */
    protected function loadRoutes(): void
    {
        $config = require dirname(__DIR__) . '/Config/config.php';
        $isProduction = ($config['app']['env'] ?? 'development') === 'production';

        // Если мы в продакшене и файл кэша существует — загружаем его за 1 микросекунду
        if ($isProduction && file_exists($this->cacheFile)) {
            $cache = require $this->cacheFile;
            $this->routes = $cache['routes'] ?? [];
            $this->namedRoutes = $cache['namedRoutes'] ?? [];
            return;
        }

        // Иначе (в режиме разработки или если кэш еще не создан) собираем маршруты вручную
        $this->loadModulesRoutes();
    }

    /**
     * Сканирование папок модулей (Локальный поиск роутов)
     */
    protected function loadModulesRoutes(): void
    {
        $modulesPath = dirname(__DIR__) . '/Modules';
        if (!is_dir($modulesPath)) return;

        $modules = array_diff(scandir($modulesPath), ['.', '..']);

        foreach ($modules as $module) {
            $routesFile = $modulesPath . '/' . $module . '/routes.php';
            if (file_exists($routesFile)) {
                $router = $this;
                require $routesFile;
            }
        }
    }

    /**
     * Регистрация маршрута в системе
     */
    public function add(string $method, string $route, string $action, ?string $name = null): void
    {
        if ($name !== null) {
            $this->namedRoutes[$name] = $route;
        }

        $regexRoute = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route);
        $regexRoute = '#^' . $regexRoute . '$#s';

        $this->routes[strtoupper($method)][$regexRoute] = $action;
    }

    /**
     * ПРИНУДИТЕЛЬНАЯ КОМПИЛЯЦИЯ КЭША (Вызывается из админки)
     */
    public function compileCache(): void
    {
        // 1. Принудительно собираем самые свежие маршруты из файлов
        $this->routes = [];
        $this->namedRoutes = [];
        $this->loadModulesRoutes();

        // 2. Формируем PHP-код для сохранения
        $cacheData = [
            'routes' => $this->routes,
            'namedRoutes' => $this->namedRoutes
        ];

        $cacheContent = "<?php" . PHP_EOL;
        $cacheContent .= "/* Автоматически сгенерированный кэш маршрутов ядра фреймворка */" . PHP_EOL;
        $cacheContent .= "/* Сгенерировано: " . date('Y-m-d H:i:s') . " */" . PHP_EOL;
        $cacheContent .= "return " . var_export($cacheData, true) . ";" . PHP_EOL;

        // 3. Создаем папку кэша, если её нет
        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        file_put_contents($this->cacheFile, $cacheContent);
    }

    /**
     * ОЧИСТКА КЭША
     */
    public function clearCache(): void
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    public function route(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            Logger::error("Попытка генерации несуществующего именованного маршрута: '{$name}'");
            return '#route-not-found';
        }
        $pattern = $this->namedRoutes[$name];
        foreach ($params as $key => $value) {
            $pattern = str_replace('{' . $key . '}', urlencode((string)$value), $pattern);
        }
        return '/' . ltrim($pattern, '/');
    }

    public function dispatch(): void
    {
        $uri = $this->request->getUri();
        $method = $this->request->getMethod();

        // --- ИНТЕГРАЦИЯ RATE LIMITER (ЗАЩИТА ЯДРА) ---
        if ($method === 'POST') {
            // 1. Если это POST-запрос на авторизацию или регистрацию — включаем сверхстрогий лимит
            if ($uri === 'login' || $uri === 'register') {
                if (!\App\Core\RateLimiter::check('auth.submit')) {
                    \App\Core\RateLimiter::block();
                }
            } else {
                // 2. Для всех остальных POST-запросов (комменты, посты, лайки) — глобальный POST-лимит
                if (!\App\Core\RateLimiter::check('global.post')) {
                    \App\Core\RateLimiter::block();
                }
            }
        } else {
            // 3. Для всех стандартных GET-запросов (просмотр страниц) — глобальный GET-лимит
            if (!\App\Core\RateLimiter::check('global.get')) {
                \App\Core\RateLimiter::block();
            }
        }
        // --- КОНЕЦ БЛОКА ЗАЩИТЫ ---

        if (!isset($this->routes[$method])) {
            $this->triggerError(404, "Method $method not allowed");
            return;
        }
        foreach ($this->routes[$method] as $route => $action) {
            if (preg_match($route, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $this->executeAction($action, $params);
                return;
            }
        }
        $this->triggerError(404, "Route not found");
    }

    protected function executeAction(string $action, array $params): void
    {
        if (strpos($action, '@') === false) {
            $this->triggerError(500, "Invalid action format: '$action'");
            return;
        }
        list($controllerName, $method) = explode('@', $action);
        $moduleName = str_replace('Controller', '', $controllerName);
        $controllerClass = "App\\Modules\\{$moduleName}\\Controllers\\{$controllerName}";
        if (!class_exists($controllerClass)) {
            $this->triggerError(500, "Controller class $controllerClass not found");
            return;
        }
        $controllerInstance = new $controllerClass();
        if (!method_exists($controllerInstance, $method)) {
            $this->triggerError(500, "Method $method not found");
            return;
        }
        call_user_func_array([$controllerInstance, $method], $params);
    }

    protected function triggerError(int $code, string $message): void
    {
        http_response_code($code);
        if ($code === 404) {
            Logger::error("Ошибка 404: " . $message, ['url' => $_SERVER['REQUEST_URI'] ?? '/']);
        }
        $errorControllerClass = "App\\Modules\\Errors\\Controllers\\ErrorsController";
        if (class_exists($errorControllerClass)) {
            $controller = new $errorControllerClass();
            $controller->notFound($message);
            return;
        }
        echo "<h1>Error $code</h1><p>$message</p>";
    }
}
