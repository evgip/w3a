<?php

declare(strict_types=1);

namespace App\Core;

use App\Modules\Users\Models\RateLimit;
use App\Modules\Errors\Controllers\ErrorsController;

class RateLimiter
{
    private Database $db;
    private Logger $logger;
    private Audit $audit;
    private IpResolver $ipResolver;
    private Container $container;
    private Config $config;
    private Request $request;

    public function __construct(
        Database $db,
        Logger $logger,
        Audit $audit,
        IpResolver $ipResolver,
        Container $container,
        Config $config,
        Request $request
    ) {
        $this->db = $db;
        $this->logger = $logger;
        $this->audit = $audit;
        $this->ipResolver = $ipResolver;
        $this->container = $container;
        $this->config = $config;
        $this->request = $request;
    }

    public function check(string $action): bool
    {
        $config = $this->config->getArray('rate_limit.rules', []);

        if (!isset($config[$action])) {
            return true; // Правила нет = пропускаем
        }

        $rule = $config[$action];
        $maxRequests = (int)($rule['max_requests'] ?? 0);
        $window = (int)($rule['window'] ?? 60);
        $enabled = (bool)($rule['enabled'] ?? true);

        if (!$enabled || $maxRequests <= 0) {
            return true;
        }

        $identifier = $this->getIdentifier();
        $rateLimitModel = $this->container->get(RateLimit::class);

        // ОДИН атомарный запрос вместо COUNT + INSERT
        $currentRequests = $rateLimitModel->incrementRequestCount($identifier, $action, $window);

        $remaining = max(0, $maxRequests - $currentRequests);

        // Отправляем стандартные заголовки RateLimit
        header("RateLimit-Limit: {$maxRequests}");
        header("RateLimit-Remaining: {$remaining}");
        header("RateLimit-Reset: {$window}");

        // Если текущий запрос превысил лимит
        if ($currentRequests > $maxRequests) {
            return false;
        }

        return true;
    }

    private function getIdentifier(): string
    {
        if (\App\Modules\Auth\Services\Auth::check()) {
            return 'user:' . \App\Modules\Auth\Services\Auth::id();
        }

        $ip = $this->ipResolver->getClientIp();
        $userAgent = $this->request->getUserAgent() ?? '';
        return 'fingerprint:' . hash('sha256', $ip . '|' . $userAgent);
    }

    public function block(): void
    {
        $ip = $this->ipResolver->getClientIp();
        $uri = $this->request->getUri();

        $this->audit->log('security.rate_limited', "Превышен лимит частоты запросов.", 'security', [
            'ip_address' => $ip,
            'url'        => $uri
        ]);

        $controller = $this->container->make(ErrorsController::class);
        $controller->tooManyRequests("Вы делаете запросы слишком часто. Пожалуйста, подождите и обновите страницу.");
    }
}
