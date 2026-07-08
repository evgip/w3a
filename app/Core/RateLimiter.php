<?php

declare(strict_types=1);

namespace App\Core;

use App\Modules\Users\Models\RateLimit;
use App\Modules\Errors\Controllers\ErrorsController;

/**
 * Rate Limiter для защиты от флуда.
 */
class RateLimiter
{
	private Database $db;
	private Logger $logger;
	private Audit $audit;
	private IpResolver $ipResolver;
	private Container $container;
	private Config $config;
	private Request $request;

	/**
	 * Конструктор с инъекцией зависимостей
	 */
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

	/**
	 * Проверить лимит запросов
	 */
	public function check(string $action): bool
	{
		$config = $this->config->getArray('rate_limit.rules', []);

		if (!isset($config[$action])) {
			return true;
		}

		$rule = $config[$action];
		$maxRequests = (int)($rule['max_requests'] ?? 0);
		$window = (int)($rule['window'] ?? 60);
		$enabled = (bool)($rule['enabled'] ?? true);

		if (!$enabled) {
			return true;
		}

		$identifier = $this->getIdentifier();

		$rateLimitModel = $this->container->get(RateLimit::class);

		// Garbage Collection
		$gcProbability = $this->config->getInt('rate_limit.gc_probability', 5);
		if (random_int(1, 100) <= $gcProbability) {
			$rateLimitModel->clearStaleLogs($window);
		}

		// Fetch current hit counters
		$currentRequests = $rateLimitModel->getRequestCount($identifier, $action, $window);

		// Persist the current tracking snapshot
		$rateLimitModel->logRequest($identifier, $action);

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
	 * Возвращает идентификатор для rate limiting
	 */
	private function getIdentifier(): string
	{
		// 1. Если пользователь авторизован — используем user_id
		if (\App\Modules\Auth\Services\Auth::check()) {
			return 'user:' . \App\Modules\Auth\Services\Auth::id();
		}

		// 2. Иначе — fingerprint (IP + User-Agent)
		$ip = $this->ipResolver->getClientIp();
		$userAgent = $this->request->getUserAgent() ?? '';
		return 'fingerprint:' . hash('sha256', $ip . '|' . $userAgent);
	}

	/**
	 * Заблокировать запрос (429 Too Many Requests)
	 */
	public function block(): void
	{
		$ip = $this->ipResolver->getClientIp();
		$uri = $this->request->getUri();

		$this->audit->log('security.rate_limited', "Превышен лимит частоты запросов. IP заблокирован.", 'security', [
			'ip_address' => $ip,
			'url'        => $uri
		]);

		$controller = $this->container->make(ErrorsController::class);
		$controller->tooManyRequests("Вы делаете запросы слишком часто. Пожалуйста, подождите и обновите страницу.");
	}
}
