<?php

declare(strict_types=1);

namespace App\Core;

use App\Modules\Users\Models\RateLimit;
use App\Modules\Errors\Controllers\ErrorsController;

/**
 * Rate Limiter для защиты от флуда.
 * 
 * ✅ ИЗМЕНЕНО: Класс теперь нестатический, зависимости внедряются через конструктор.
 */
class RateLimiter
{
    private Database $db;
    private Logger $logger;
    private Audit $audit;
    private IpResolver $ipResolver;
    private Container $container;

    /**
     * ✅ Конструктор с инъекцией зависимостей
     */
    public function __construct(
        Database $db,
        Logger $logger,
        Audit $audit,
        IpResolver $ipResolver,
        Container $container
    ) {
        $this->db = $db;
        $this->logger = $logger;
        $this->audit = $audit;
        $this->ipResolver = $ipResolver;
        $this->container = $container;
    }

    /**
     * Evaluate incoming request frequencies against configuration constraints
     */
    public function check(string $action): bool
    {
        $config = Config::getArray('rate_limit', []);

        if (!($config['enabled'] ?? false) || !isset($config['rules'][$action])) {
            return true;
        }

        $rule = $config['rules'][$action];
        $maxRequests = (int)$rule['max_requests'];
        $window = (int)$rule['window'];

        // ✅ Используем внедрённый IpResolver
        $ip = $this->ipResolver->getClientIp();
        
        // ✅ Получаем модель из контейнера
        $rateLimitModel = $this->container->get(RateLimit::class);

        // 1. Garbage Collection
        $gcProbability = Config::getInt('rate_limit.gc_probability', 5);
        if (random_int(1, 100) <= $gcProbability) {
            $rateLimitModel->clearStaleLogs($window);
        }

        // 2. Fetch current hit counters
        $currentRequests = $rateLimitModel->getRequestCount($ip, $action, $window);

        // 3. Persist the current tracking snapshot
        $rateLimitModel->logRequest($ip, $action);

        $remaining = max(0, $maxRequests - ($currentRequests + 1));

        // Dispatch headers
        header("RateLimit-Limit: {$maxRequests}");
        header("RateLimit-Remaining: {$remaining}");
        header("RateLimit-Reset: {$window}");

        if (($currentRequests + 1) > $maxRequests) {
            return false;
        }

        return true;
    }

    /**
     * Halt runtime processing and output a fully clean HTTP 429 page
     */
    public function block(): void
    {
        // ✅ Используем внедрённый IpResolver
        $ip = $this->ipResolver->getClientIp();

        // ✅ Используем внедрённый Audit
        $this->audit->log('security.rate_limited', "Превышен лимит частоты запросов. IP заблокирован.", 'security', [
            'ip_address' => $ip,
            'url'        => $_SERVER['REQUEST_URI'] ?? '/'
        ]);

        // ✅ Создаём контроллер через контейнер
        $controller = $this->container->make(ErrorsController::class);
        $controller->tooManyRequests("Вы делаете запросы слишком часто. Пожалуйста, подождите и обновите страницу.");
    }
}