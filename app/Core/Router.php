<?php

namespace App\Core;

use App\Core\Middleware\MiddlewarePipeline;
use App\Core\Events\EventDispatcher;
use App\Providers\EventServiceProvider;

class Router
{
    // ============================================
    // СВОЙСТВА КЛАССА (все должны быть объявлены!)
    // ============================================
    
    /** @var string|null Имя текущего маршрута (для is_route()) */
    protected ?string $currentRouteName = null;
    
    /** @var self|null Singleton экземпляр */
    private static ?self $instance = null;
    
    /** @var array Маршруты, сгруппированные по HTTP-методу */
    protected array $routes = [];
    
    /** @var array Именованные маршруты */
    protected array $namedRoutes = [];
    
    /** @var array Middleware для каждого маршрута */
    protected array $routeMiddleware = [];
    
    /** @var Request Объект запроса */
    protected Request $request;
    
    /** @var string Путь к файлу кэша маршрутов */
    protected string $cacheFile;
	
	protected ?EventDispatcher $eventDispatcher = null;
    
    /** @var array Группы middleware (алиасы) */
	protected array $middlewareGroups = [
		'web' => [
			\App\Core\Middleware\CsrfMiddleware::class,
		],
		'auth' => [
			\App\Core\Middleware\AuthMiddleware::class,
		],
		'guest' => [
			\App\Core\Middleware\GuestMiddleware::class,
		],
		'moderator' => [
			\App\Core\Middleware\ModeratorMiddleware::class,
		],
		'admin' => [
			\App\Core\Middleware\AdminMiddleware::class,
		],
	];
	
    /** @var array Текущий контекст группы маршрутов */
    protected array $currentGroupMiddleware = [];
    
    /** @var string Текущий префикс группы маршрутов */
    protected string $currentGroupPrefix = '';

    // ============================================
    // КОНСТРУКТОР
    // ============================================
    
    public function __construct(Request $request)
    {
        // Сохраняем себя как singleton
        self::$instance = $this;
        
        $this->request = $request;
        $this->cacheFile = dirname(__DIR__, 2) . '/storage/cache/routes_compiled.php';
        $this->loadRoutes();
    }
    
    // ============================================
    // SINGLETON
    // ============================================
    
    /**
     * Получить текущий экземпляр Router
     */
    public static function getInstance(): ?self
    {
        return self::$instance;
    }
    
    // ============================================
    // ГЕТТЕРЫ
    // ============================================
    
    /**
     * Получить имя текущего маршрута (для is_route())
     */
    public function getCurrentRouteName(): ?string
    {
        return $this->currentRouteName;
    }
    
    /**
     * Получить все именованные маршруты
     */
    public function getNamedRoutes(): array
    {
        return $this->namedRoutes;
    }

    // ============================================
    // ЗАГРУЗКА МАРШРУТОВ (оригинальная логика)
    // ============================================

    /**
     * Умная загрузка маршрутов: Кэш (Production) vs Сканирование (Development)
     */
    protected function loadRoutes(): void
    {
        $config = require dirname(__DIR__) . '/Config/config.php';
        $isProduction = ($config['app']['env'] ?? 'development') === 'production';
        
        // Production + кэш существует → загружаем из кэша
        if ($isProduction && file_exists($this->cacheFile)) {
            $cache = require $this->cacheFile;
            $this->routes = $cache['routes'] ?? [];
            $this->namedRoutes = $cache['namedRoutes'] ?? [];
            $this->routeMiddleware = $cache['routeMiddleware'] ?? [];
            return;
        }
        
        // Development или кэш не создан → сканируем модули
        $this->loadModulesRoutes();
    }

    /**
     * Сканирование папок модулей (оригинальная логика)
     */
    protected function loadModulesRoutes(): void
    {
        $modulesPath = dirname(__DIR__) . '/Modules';
        if (!is_dir($modulesPath)) {
            return;
        }
        
        $modules = array_diff(scandir($modulesPath), ['.', '..']);
        
        foreach ($modules as $module) {
            $routesFile = $modulesPath . '/' . $module . '/routes.php';
            if (file_exists($routesFile)) {
                $router = $this;
                require $routesFile;
            }
        }
    }

    // ============================================
    // РЕГИСТРАЦИЯ МАРШРУТОВ
    // ============================================

    /**
     * Регистрация маршрута
     * 
     * @param string $method HTTP-метод (GET, POST, etc.)
     * @param string $route URI маршрута (например, '/story/{id}')
     * @param string $action Контроллер@метод (FQCN)
     * @param string|null $name Имя маршрута
     * @param array $middleware Middleware для этого маршрута
     */
    public function add(
        string $method, 
        string $route, 
        string $action, 
        ?string $name = null,
        array $middleware = []
    ): void {
        // Добавляем префикс группы, если есть
        $fullRoute = $this->currentGroupPrefix . $route;
        
        // Объединяем middleware группы и индивидуальные
        $allMiddleware = array_merge($this->currentGroupMiddleware, $middleware);
        
        // Компилируем regex для маршрута
        $regexRoute = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $fullRoute);
        $regexRoute = '#^' . $regexRoute . '$#s';
        
        // Сохраняем маршрут
        $this->routes[strtoupper($method)][$regexRoute] = [
            'action' => $action,
            'original_uri' => $fullRoute,
            'name' => $name,  // ← сохраняем имя маршрута
        ];
        
        // Сохраняем middleware
        $this->routeMiddleware[$regexRoute] = $allMiddleware;
        
        // Именованный маршрут
        if ($name !== null) {
            $this->namedRoutes[$name] = $fullRoute;
        }
    }

    /**
     * Группа маршрутов с общим middleware и префиксом
     */
    public function group(array $options, callable $callback): void
    {
        // Сохраняем предыдущее состояние
        $previousMiddleware = $this->currentGroupMiddleware;
        $previousPrefix = $this->currentGroupPrefix;
        
        // Применяем настройки группы
        $middleware = $options['middleware'] ?? [];
        $prefix = $options['prefix'] ?? '';
        
        // Разворачиваем алиасы middleware (web, auth, guest)
        $expandedMiddleware = [];
        foreach ($middleware as $m) {
            if (isset($this->middlewareGroups[$m])) {
                $expandedMiddleware = array_merge($expandedMiddleware, $this->middlewareGroups[$m]);
            } else {
                $expandedMiddleware[] = $m;
            }
        }
        
        $this->currentGroupMiddleware = array_merge($previousMiddleware, $expandedMiddleware);
        $this->currentGroupPrefix = $previousPrefix . $prefix;
        
        // Выполняем callback
        $callback($this);
        
        // Восстанавливаем состояние
        $this->currentGroupMiddleware = $previousMiddleware;
        $this->currentGroupPrefix = $previousPrefix;
    }

    // ============================================
    // КЭШИРОВАНИЕ
    // ============================================

    /**
     * Принудительная компиляция кэша (вызывается из админки)
     */
    public function compileCache(): void
    {
        // 1. Пересобираем маршруты
        $this->routes = [];
        $this->namedRoutes = [];
        $this->routeMiddleware = [];
        $this->loadModulesRoutes();
        
        // 2. Формируем данные для кэша
        $cacheData = [
            'routes' => $this->routes,
            'namedRoutes' => $this->namedRoutes,
            'routeMiddleware' => $this->routeMiddleware,
        ];
        
        // 3. Генерируем PHP-код
        $cacheContent = "<?php" . PHP_EOL;
        $cacheContent .= "/* Автоматически сгенерированный кэш маршрутов */" . PHP_EOL;
        $cacheContent .= "/* Сгенерировано: " . date('Y-m-d H:i:s') . " */" . PHP_EOL;
        $cacheContent .= "return " . var_export($cacheData, true) . ";" . PHP_EOL;
        
        // 4. Сохраняем
        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        file_put_contents($this->cacheFile, $cacheContent);
    }

    /**
     * Очистка кэша
     */
    public function clearCache(): void
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    // ============================================
    // ГЕНЕРАЦИЯ URL
    // ============================================

    /**
     * Генерация URL по имени маршрута
     * 
     * @example route('home') → '/'
     * @example route('story.show', ['id' => 123]) → '/story/123'
     */
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

    // ============================================
    // ОБРАБОТКА ЗАПРОСА
    // ============================================

    /**
     * Диспетчеризация запроса
     */
    public function dispatch(): void
    {
		// ✅ ИНИЦИАЛИЗАЦИЯ EVENT DISPATCHER (один раз на весь запрос)
		if ($this->eventDispatcher === null) {
			$this->eventDispatcher = new EventDispatcher();
			EventServiceProvider::register($this->eventDispatcher);
		}
		
        $uri = $this->request->getUri();
        $method = $this->request->getMethod();

        // --- RATE LIMITER ---
        if ($method === 'POST') {
            if ($uri === 'login' || $uri === 'register') {
                if (!\App\Core\RateLimiter::check('auth.submit')) {
                    \App\Core\RateLimiter::block();
                }
            } else {
                if (!\App\Core\RateLimiter::check('global.post')) {
                    \App\Core\RateLimiter::block();
                }
            }
        } else {
            if (!\App\Core\RateLimiter::check('global.get')) {
                \App\Core\RateLimiter::block();
            }
        }
        // --- КОНЕЦ RATE LIMITER ---

        if (!isset($this->routes[$method])) {
            $this->triggerError(404, "Method $method not allowed");
            return;
        }

        // Ищем подходящий маршрут
        foreach ($this->routes[$method] as $routeRegex => $routeData) {
            if (preg_match($routeRegex, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                
                // Сохраняем имя текущего маршрута (для is_route())
                $this->currentRouteName = $routeData['name'] ?? null;
                
                // Получаем middleware для этого маршрута
                $middleware = $this->routeMiddleware[$routeRegex] ?? [];
                
                // Запускаем через pipeline
                $this->executeWithMiddleware($routeData['action'], $params, $middleware);
                return;
            }
        }

        $this->triggerError(404, "Route not found");
    }

    /**
     * Выполнение действия через middleware pipeline
     */
    protected function executeWithMiddleware(string $action, array $params, array $middleware): void
    {
        if (empty($middleware)) {
            // Нет middleware — выполняем напрямую
            $this->executeAction($action, $params);
            return;
        }
        
        // Создаём pipeline
        $pipeline = new MiddlewarePipeline();
        
        foreach ($middleware as $middlewareClass) {
            if (class_exists($middlewareClass)) {
                $pipeline->pipe($middlewareClass);
            } else {
                Logger::warning("Middleware class not found: {$middlewareClass}");
            }
        }
        
        // Финальное действие — вызов контроллера
        $destination = function() use ($action, $params) {
            $this->executeAction($action, $params);
        };
        
        // Запускаем
        $pipeline->process($destination);
    }

    /**
     * Выполнение контроллера
     */
    protected function executeAction(string $action, array $params): void
    {
        if (strpos($action, '@') === false) {
            $this->triggerError(500, "Invalid action format: '$action'");
            return;
        }
        
        [$controllerClass, $method] = explode('@', $action);
        
        // Проверяем существование класса
        if (!class_exists($controllerClass)) {
            $this->triggerError(500, "Controller class not found: $controllerClass");
            return;
        }
        
		// ✅ Передаём Request и EventDispatcher
		$controllerInstance = new $controllerClass($this->request, $this->eventDispatcher);
		
        if (!method_exists($controllerInstance, $method)) {
            $this->triggerError(500, "Method $method not found in $controllerClass");
            return;
        }
        
        call_user_func_array([$controllerInstance, $method], $params);
    }

    // ============================================
    // ОБРАБОТКА ОШИБОК
    // ============================================

    protected function triggerError(int $code, string $message): void
    {
        http_response_code($code);
        
        if ($code === 404) {
            Logger::error("Ошибка 404: " . $message, ['url' => $_SERVER['REQUEST_URI'] ?? '/']);
        }
        
        $errorControllerClass = "App\\Modules\\Errors\\Controllers\\ErrorsController";
        
        if (class_exists($errorControllerClass)) {
            $controller = new $errorControllerClass();
            
            match($code) {
                404 => $controller->notFound($message),
                403 => $controller->forbidden($message),
                419 => $controller->csrf($message),
                default => $controller->notFound($message),
            };
            return;
        }
        
        // Fallback
        echo "<h1>Error $code</h1><p>" . htmlspecialchars($message) . "</p>";
    }
}