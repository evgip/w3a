<?php

namespace App\Core;

use App\Core\Events\EventDispatcher;

class ModuleServiceProvider
{
    protected ?Container $container = null;
    protected ?Request $request = null;

    public function __construct(?Request $request = null)
    {
        $this->request = $request;
    }

    /**
     * Регистрация сервисов в контейнере
     */
    public function register(Container $container): void
    {
        $this->container = $container;

        // 1. Request
        if ($this->request !== null) {
			$container->singleton(Request::class, function($container) {
				$request = $this->request;
				$request->setAudit($container->get(Audit::class));
				$request->setSession($container->get(Session::class));
				$request->setContainer($container);
				
				return $request;
			});
        }

        // 2. Database (singleton — одно подключение на весь запрос)
        $container->singleton(Database::class, function($container) {
            $config = require dirname(__DIR__) . '/Config/config.php';
            return new Database($config['database'] ?? []);
        });

        // 3. Session (singleton — одна сессия на весь запрос)
        $container->singleton(Session::class, function($container) {
            return new Session();
        });

        // 4. Logger (singleton — один логгер на весь запрос)
        $container->singleton(Logger::class, function($container) {
            $logFile = dirname(__DIR__, 2) . '/storage/logs/app.log';
            return new Logger($logFile);
        });

        // 5. IpResolver (singleton — один резолвер на весь запрос)
        $container->singleton(IpResolver::class, function($container) {
            return new IpResolver();
        });

        // 6. Audit (singleton — один сервис аудита на весь запрос)
        $container->singleton(Audit::class, function($container) {
            return new Audit(
                $container->get(Database::class),
                $container->get(Session::class),
                $container->get(IpResolver::class)
            );
        });

        // 7. Validator (transient — новый экземпляр каждый раз)
		$container->bind(Validator::class, function($container) {
			return new Validator($container->get(Database::class));
		});

        // 8. Rate Limiter
        $container->singleton(RateLimiter::class, function($container) {
            return new RateLimiter(
                $container->get(Database::class),
                $container->get(Logger::class),
                $container->get(Audit::class),
                $container->get(IpResolver::class),
                $container  // сам контейнер
            );
        });

        // 9. Event Dispatcher (если ещё не зарегистрирован)
        if (!$container->has(EventDispatcher::class)) {
            $container->singleton(EventDispatcher::class, function($container) {
                return new EventDispatcher();
            });
        }

        // 10. Container (сам себя)
        $container->instance(Container::class, $container);
		
		// 11. Security
		$container->singleton(Security::class, function($container) {
			return new Security(
				$container->get(Logger::class)
			);
		});
    }

    /**
     * Загрузка сервисов (вызывается после регистрации)
     */
    public function boot(): void
    {
        // Здесь можно инициализировать сервисы, которые зависят от других сервисов
    }
}