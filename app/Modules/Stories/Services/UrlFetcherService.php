<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

/**
 * Сервис для извлечения метаданных из URL (заголовок, canonical URL)
 * Аналог функционала из Lobsters
 */
class UrlFetcherService
{
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
            // Загружаем содержимое URL
            $content = $this->fetchUrl($url);

            if ($content === false) {
                return $result;
            }

            // Определяем тип контента
            $contentType = $this->getContentType($url);

            if (stripos($contentType, 'text/html') !== false) {
                $result = $this->parseHtml($content, $result);
            } elseif (stripos($contentType, 'application/pdf') !== false) {
                $result = $this->parsePdf($content, $result);
            }
        } catch (\Exception $e) {
            // Логируем ошибку, но не прерываем выполнение
            error_log("UrlFetcherService error: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Загрузить содержимое URL
     */
    private function fetchUrl(string $url)
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'w3a/1.0 (+https://w3a.app)',
                'follow_location' => true,
                'max_redirects' => 5,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $content = @file_get_contents($url, false, $context);

        if ($content === false) {
            return false;
        }

        return $content;
    }

    /**
     * Определить тип контента
     */
    private function getContentType(string $url): string
    {
        $headers = @get_headers($url, true);

        if (isset($headers['Content-Type'])) {
            if (is_array($headers['Content-Type'])) {
                return end($headers['Content-Type']);
            }
            return $headers['Content-Type'];
        }

        // Определяем по расширению
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            return 'application/pdf';
        }

        return 'text/html';
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
