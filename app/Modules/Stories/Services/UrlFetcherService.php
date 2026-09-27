<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

/**
 * Сервис для извлечения метаданных из URL (заголовок, canonical URL)
 * Аналог функционала из Lobsters
 */
class UrlFetcherService
{
    private const MAX_REDIRECTS = 5;
    private const MAX_RESPONSE_BYTES = 2097152;

    /**
     * Извлечь заголовок и другие атрибуты из URL
     */
    public function fetchAttributes(string $url): array
    {
        $result = [
            'title' => '',
            'url' => $url,
        ];

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return $result;
        }

        try {
            $response = $this->fetchUrl($url);

            if ($response === null) {
                return $result;
            }

            if ($response['content_type'] === 'text/html') {
                $result = $this->parseHtml($response['body'], $result);
            } elseif ($response['content_type'] === 'application/pdf') {
                $result = $this->parsePdf($response['body'], $result);
            }
        } catch (\Exception $e) {
            error_log("UrlFetcherService error: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Загрузить содержимое URL, повторно проверяя каждую цель редиректа.
     *
     * @return array{body: string, content_type: string}|null
     */
    private function fetchUrl(string $url): ?array
    {
        $currentUrl = $url;

        for ($redirectCount = 0; $redirectCount <= self::MAX_REDIRECTS; $redirectCount++) {
            $target = $this->validateTarget($currentUrl);
            if ($target === null) {
                return null;
            }

            $body = '';
            $contentType = '';
            $location = '';
            $handle = curl_init($currentUrl);
            if ($handle === false) {
                return null;
            }

            $options = [
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_NOSIGNAL => true,
                CURLOPT_PROXY => '',
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'w3a/1.0 (+https://w3a.app)',
                CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$contentType, &$location): int {
                    $length = strlen($headerLine);
                    if (stripos($headerLine, 'Content-Type:') === 0) {
                        $contentType = strtolower(trim(explode(';', trim(substr($headerLine, 13)), 2)[0]));
                    } elseif (stripos($headerLine, 'Location:') === 0) {
                        $location = trim(substr($headerLine, 9));
                    }
                    return $length;
                },
                CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body): int {
                    $chunkLength = strlen($chunk);
                    if (strlen($body) + $chunkLength > self::MAX_RESPONSE_BYTES) {
                        return 0;
                    }
                    $body .= $chunk;
                    return $chunkLength;
                },
            ];

            if ($target['resolve'] !== null) {
                $options[CURLOPT_RESOLVE] = [$target['resolve']];
            }

            if (!curl_setopt_array($handle, $options)) {
                curl_close($handle);
                return null;
            }

            $response = curl_exec($handle);
            $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_close($handle);

            if ($response === false) {
                return null;
            }

            if (in_array($statusCode, [301, 302, 303, 307, 308], true)) {
                if ($redirectCount === self::MAX_REDIRECTS || $location === '') {
                    return null;
                }
                $nextUrl = $this->resolveRedirect($currentUrl, $location);
                if ($nextUrl === null) {
                    return null;
                }
                $currentUrl = $nextUrl;
                continue;
            }

            if ($statusCode < 200 || $statusCode >= 300) {
                return null;
            }

            return ['body' => $body, 'content_type' => $contentType];
        }

        return null;
    }

    /**
     * Разрешает только публичный HTTP(S)-адрес и закрепляет проверенный DNS-ответ в cURL.
     *
     * @return array{host: string, port: int, resolve: ?string}|null
     */
    private function validateTarget(string $url): ?array
    {
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1) {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $expectedPort = $scheme === 'https' ? 443 : 80;
        $port = (int) ($parts['port'] ?? $expectedPort);
        if ($port !== $expectedPort) {
            return null;
        }

        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        if ($host === '' || str_ends_with($host, '.')) {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if (!$this->isPublicIp($host)) {
                return null;
            }
            return ['host' => $host, 'port' => $port, 'resolve' => null];
        }

        $legacyIpPattern = '/\A(?:0x[0-9a-f]+|[0-9]+)(?:\.(?:0x[0-9a-f]+|[0-9]+)){0,3}\z/i';
        if (preg_match($legacyIpPattern, $host) === 1
            || preg_match('/\A(?=.{1,253}\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?))*\z/iD', $host) !== 1) {
            return null;
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (!is_array($records) || $records === []) {
            return null;
        }

        $addresses = [];
        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            if (!$this->isPublicIp($address)) {
                return null;
            }
        }

        $resolvedAddress = $addresses[0];
        if (str_contains($resolvedAddress, ':')) {
            $resolvedAddress = '[' . $resolvedAddress . ']';
        }

        return [
            'host' => $host,
            'port' => $port,
            'resolve' => $host . ':' . $port . ':' . $resolvedAddress,
        ];
    }

    private function isPublicIp(string $ip): bool
    {
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        if (filter_var($ip, FILTER_VALIDATE_IP, $flags) === false) {
            return false;
        }

        $packed = inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        if (strlen($packed) === 4) {
            $firstOctet = ord($packed[0]);
            $secondOctet = ord($packed[1]);
            if ($firstOctet === 100 && $secondOctet >= 64 && $secondOctet <= 127) {
                return false;
            }
            return true;
        }

        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
            $mappedIpv4 = inet_ntop(substr($packed, 12));
            return $mappedIpv4 !== false && filter_var($mappedIpv4, FILTER_VALIDATE_IP, $flags) !== false;
        }

        return (ord($packed[0]) & 0xe0) === 0x20;
    }

    private function resolveRedirect(string $currentUrl, string $location): ?string
    {
        $location = trim($location);
        if ($location === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $location) === 1) {
            return null;
        }

        if (preg_match('/\Ahttps?:\/\//i', $location) === 1) {
            return explode('#', $location, 2)[0];
        }
        if (str_starts_with($location, '//')) {
            $scheme = (string) parse_url($currentUrl, PHP_URL_SCHEME);
            return $scheme . ':' . explode('#', $location, 2)[0];
        }

        $base = parse_url($currentUrl);
        $relative = parse_url($location);
        if (!is_array($base) || !is_array($relative) || isset($relative['host'])) {
            return null;
        }

        $scheme = (string) ($base['scheme'] ?? '');
        $host = (string) ($base['host'] ?? '');
        if (str_contains($host, ':') && !str_starts_with($host, '[')) {
            $host = '[' . $host . ']';
        }
        $port = isset($base['port']) ? ':' . $base['port'] : '';
        $basePath = (string) ($base['path'] ?? '/');
        $relativePath = (string) ($relative['path'] ?? '');

        if ($relativePath === '') {
            $path = $basePath;
            $query = array_key_exists('query', $relative)
                ? '?' . $relative['query']
                : (isset($base['query']) ? '?' . $base['query'] : '');
        } else {
            if (str_starts_with($relativePath, '/')) {
                $path = $relativePath;
            } else {
                $directory = substr($basePath, 0, (int) strrpos($basePath, '/') + 1);
                $path = $directory . $relativePath;
            }

            $trailingSlash = str_ends_with($path, '/') || str_ends_with($path, '/.') || str_ends_with($path, '/..');
            $segments = [];
            foreach (explode('/', $path) as $segment) {
                if ($segment === '' || $segment === '.') {
                    continue;
                }
                if ($segment === '..') {
                    array_pop($segments);
                    continue;
                }
                $segments[] = $segment;
            }
            $path = '/' . implode('/', $segments);
            if ($trailingSlash && !str_ends_with($path, '/')) {
                $path .= '/';
            }
            $query = isset($relative['query']) ? '?' . $relative['query'] : '';
        }

        return $scheme . '://' . $host . $port . $path . $query;
    }

    /**
     * Парсить HTML и извлечь заголовок
     */
    private function parseHtml(string $html, array $result): array
    {
        // Подавляем ошибки парсинга
        libxml_use_internal_errors(true);

        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR);

        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);

        // 1. Пытаемся получить Open Graph заголовок
        $ogTitle = $xpath->query("//meta[@property='og:title']/@content");
        if ($ogTitle->length > 0) {
            $result['title'] = trim($ogTitle->item(0)->nodeValue);
        }

        // 2. Если не найден, пробуем <meta name="title">
        if (empty($result['title'])) {
            $metaTitle = $xpath->query("//meta[@name='title']/@content");
            if ($metaTitle->length > 0) {
                $result['title'] = trim($metaTitle->item(0)->nodeValue);
            }
        }

        // 3. Если и этого нет, используем обычный <title>
        if (empty($result['title'])) {
            $titleNodes = $xpath->query("//title");
            if ($titleNodes->length > 0) {
                $result['title'] = trim($titleNodes->item(0)->nodeValue);
            }
        }

        // 4. Удаляем название сайта из конца заголовка
        $ogSiteName = $xpath->query("//meta[@property='og:site_name']/@content");
        if ($ogSiteName->length > 0) {
            $siteName = trim($ogSiteName->item(0)->nodeValue);
            if (!empty($siteName) && mb_strpos($result['title'], $siteName) !== false) {
                // Удаляем разделители типа " - ", " | ", " – "
                $result['title'] = preg_replace('/[\s]*[-|–—][\s]*' . preg_quote($siteName, '/') . '[\s]*$/u', '', $result['title']);
                $result['title'] = trim($result['title']);
            }
        }

        // 5. Специальная обработка для GitHub
        if (stripos($result['title'], 'GitHub -') === 0) {
            $result['title'] = preg_replace('/^GitHub\s*-\s*[^:]+:\s*/i', '', $result['title']);
        }

        // 6. Пытаемся получить canonical URL
        $canonical = $xpath->query("//link[@rel='canonical']/@href");
        if ($canonical->length > 0) {
            $canonicalUrl = trim($canonical->item(0)->nodeValue);
            if (!empty($canonicalUrl) && filter_var($canonicalUrl, FILTER_VALIDATE_URL)) {
                $result['url'] = $canonicalUrl;
            }
        }

        // Ограничиваем длину заголовка
        if (mb_strlen($result['title']) > 255) {
            $result['title'] = mb_substr($result['title'], 0, 252) . '...';
        }

        return $result;
    }

    /**
     * Парсить PDF и извлечь заголовок
     */
    private function parsePdf(string $content, array $result): array
    {
        // Простое извлечение заголовка из PDF метаданных
        // Для полноценной работы нужна библиотека типа smalot/pdfparser

        // Ищем /Title в PDF
        if (preg_match('/\/Title\s*\(([^)]+)\)/i', $content, $matches)) {
            $result['title'] = trim($matches[1]);
        }

        return $result;
    }
}
