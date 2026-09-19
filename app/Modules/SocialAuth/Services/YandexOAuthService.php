<?php

declare(strict_types=1);

namespace App\Modules\SocialAuth\Services;

use W3a\Core\Support\Logger;

class YandexOAuthService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private Logger $logger;

    private const AUTH_URL = 'https://oauth.yandex.ru/authorize';
    private const TOKEN_URL = 'https://oauth.yandex.ru/token';
    private const USER_INFO_URL = 'https://login.yandex.ru/info';

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->clientId = (string)config('social_auth.yandex.client_id', '');
        $this->clientSecret = (string)config('social_auth.yandex.client_secret', '');
        $this->redirectUri = config('app.url') . '/auth/yandex/callback';
    }

    /**
     * URL для редиректа на авторизацию Яндекса.
     */
    public function getAuthUrl(string $state): string
    {
        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
            // Без явных прав (scopes) Яндекс возвращает /info без id и email.
            'scope' => 'login:info login:email',
        ]);

        return self::AUTH_URL . '?' . $params;
    }

    /**
     * Обменять код авторизации на токен.
     */
    public function exchangeCode(string $code): ?array
    {
        $response = $this->httpPost(self::TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        if (isset($response['error'])) {
            $this->logger->error('Yandex OAuth token error: ' . ($response['error_description'] ?? 'Unknown'));
            return null;
        }

        return $response;
    }

    /**
     * Получить информацию о пользователе.
     *
     * Рабочий вариант как на rss.local: прямой GET на login.yandex.ru/info
     * с заголовком Authorization, без ?format=json.
     */
    public function getUserInfo(string $accessToken): ?array
    {
        $ch = curl_init(self::USER_INFO_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: OAuth ' . $accessToken,
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            $this->logger->error('Yandex profile error', [
                'http_code' => $httpCode,
            ]);
            return null;
        }

        $profile = json_decode($response, true);
        if (!is_array($profile)) {
            $this->logger->error('Yandex /info returned non-JSON', [
                'body' => substr((string)$response, 0, 500),
            ]);
            return null;
        }

        $this->logger->info('Yandex /info keys', ['keys' => array_keys($profile)]);

        return $profile;
    }

    private function httpPost(string $url, array $data): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->logger->error('Yandex POST error: ' . $error);
            return [];
        }

        return json_decode((string)$response, true) ?? [];
    }

    private function httpGet(string $url, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->logger->error('Yandex GET error: ' . $error);
            return [];
        }

        return json_decode((string)$response, true) ?? [];
    }
}