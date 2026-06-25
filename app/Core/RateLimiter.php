<?php

namespace App\Core;

use App\Modules\Users\Models\RateLimit;

class RateLimiter
{
    /**
     * Evaluate incoming request frequencies against configuration constraints
     */
    public static function check(string $action): bool
    {
        // 🔑 Используем Config::getArray() вместо прямого require
        $config = Config::getArray('rate_limit', []);

        if (!($config['enabled'] ?? false) || !isset($config['rules'][$action])) {
            return true;
        }

        $rule = $config['rules'][$action];
        $maxRequests = (int)$rule['max_requests'];
        $window = (int)$rule['window'];

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $rateLimitModel = new RateLimit();

        // 1. Garbage Collection: Prune database rows with a 5% lottery probability
        $gcProbability = Config::getInt('rate_limit.gc_probability', 5);
        if (random_int(1, 100) <= $gcProbability) {
            $rateLimitModel->clearStaleLogs($window);
        }

        // 2. Fetch current hit counters from our clean model layer
        $currentRequests = $rateLimitModel->getRequestCount($ip, $action, $window);

        // 3. Persist the current operational tracking snapshot down to MySQL
        $rateLimitModel->logRequest($ip, $action);

        $remaining = max(0, $maxRequests - ($currentRequests + 1));

        // Dispatch explicit standardized throttle metadata tracking headers
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
    public static function block(): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Log critical rate-limiting throttle breaches down to the database for administrative alerting
        Audit::log('security.rate_limited', "Превышен лимит частоты запросов. IP заблокирован.", 'security', [
            'ip_address' => $ip,
            'url'        => $_SERVER['REQUEST_URI'] ?? '/'
        ]);

        http_response_code(429); // Too Many Requests

        $retryAfter = Config::getInt('rate_limit.retry_after', 60);
        header("Retry-After: {$retryAfter}");

        // 100% CSP compliant template framework - zero style="..." parameters used
        echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title>429 Too Many Requests</title>';
        echo '<link rel="stylesheet" href="/css/app.min.css"></head><body class="blocked-error-body">';
        echo '<div class="profile-container blocked-error-card">';
        echo '<h2 class="blocked-error-title">💥 Превышен лимит запросов (429)</h2>';
        echo '<p class="admin-subtitle-desc">Вы делаете запросы слишком часто. Пожалуйста, подождите одну минуту и обновите страницу.</p>';
        echo '<a href="/" class="btn-nav-create blocked-error-btn">Вернуться на главную</a>';
        echo '</div></body></html>';
        exit;
    }
}