<?php

declare(strict_types=1);

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

        // 1. Request (если передан)
        if ($this->request !== null) {
            $container->singleton(Request::class, function ($container) {
                $request = $this->request;
                $request->setSession($container->get(Session::class));
                $request->setAudit($container->get(Audit::class));
                $request->setContainer($container);
                return $request;
            });
        }

        // 2. Database (singleton — одно подключение на весь запрос)
        $container->singleton(Database::class, function ($container) {
            $config = $container->get(Config::class);
            return new Database($config->getArray('config.database', []));
        });

        // 3. Session (singleton — одна сессия на весь запрос)
        $container->singleton(Session::class, function ($container) {
            return new Session();
        });

        // 4. Logger (singleton — один логгер на весь запрос)
        $container->singleton(Logger::class, function ($container) {
            $logFile = dirname(__DIR__, 2) . '/storage/logs/app.log';
            return new Logger($logFile);
        });

        // 5. IpResolver (singleton — один резолвер на весь запрос)
        $container->singleton(IpResolver::class, function ($container) {
            $config = $container->get(Config::class);
            $trustedProxies = $config->getArray('config.app.trusted_proxies', []);
            return new IpResolver($trustedProxies);
        });

        // 6. Audit (singleton — один сервис аудита на весь запрос)
        $container->singleton(Audit::class, function ($container) {
            return new Audit(
                $container->get(Database::class),
                $container->get(Session::class),
                $container->get(IpResolver::class)
            );
        });

        // 7. Validator (transient — новый экземпляр каждый раз)
        $container->bind(Validator::class, function ($container) {
            return new Validator($container->get(Database::class));
        });

        // 8. Rate Limiter (singleton)
        $container->singleton(RateLimiter::class, function ($container) {
            return new RateLimiter(
                $container->get(Database::class),
                $container->get(Logger::class),
                $container->get(Audit::class),
                $container->get(IpResolver::class),
                $container,
                $container->get(Config::class),
                $container->get(Request::class)
            );
        });

        // 9. Router (singleton)
        $container->singleton(Router::class, function ($container) {
            return new Router(
                $container->get(Request::class),
                $container,
                $container->get(Config::class)
            );
        });

        // 10. Security (singleton)
        $container->singleton(Security::class, function ($container) {
            return new Security($container->get(Logger::class));
        });

        // 11. Container (сам себя)
        $container->instance(Container::class, $container);

        // 12. Event Dispatcher (если ещё не зарегистрирован)
        if (!$container->has(EventDispatcher::class)) {
            $container->singleton(EventDispatcher::class, function ($container) {
                return new EventDispatcher();
            });
        }

		// 13. View (singleton — рендерер шаблонов)
		$container->singleton(View::class, function(Container $c) {
			return new View();
		});

        $container->singleton(\App\Modules\Stories\Services\UrlFetcherService::class, function ($container) {
            return new \App\Modules\Stories\Services\UrlFetcherService();
        });

        // FileCache
        $container->singleton(\App\Core\Cache\FileCache::class, function ($container) {
            $cacheDir = dirname(__DIR__, 2) . '/storage/cache/data';
            return new \App\Core\Cache\FileCache($cacheDir);
        });

        // DatabaseCache (декоратор для Database)
        $container->singleton(\App\Core\Cache\DatabaseCache::class, function ($container) {
            $config = $container->get(\App\Core\Config::class);
            $enabled = $config->getBool('config.cache.database.enabled', true);
            $ttl = $config->getInt('config.cache.database.ttl', 3600);

            return new \App\Core\Cache\DatabaseCache(
                $container->get(\App\Core\Database::class),
                $container->get(\App\Core\Cache\FileCache::class),
                $enabled,
                $ttl
            );
        });
		
    }

    /**
     * Загрузка сервисов
     */
    public function boot(): void
    {
        // Здесь можно инициализировать сервисы
    }
}
