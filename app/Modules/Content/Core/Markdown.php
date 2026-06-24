<?php

namespace App\Modules\Content\Core;

use App\Core\Config;

/**
 * Markdown parser using Parsedown library
 * 
 * Features:
 * - Full Markdown support via Parsedown
 * - XSS protection via SafeMode
 * - Custom @mentions support
 * - File-based caching for performance
 * - Two modes: full (posts) and restricted (comments)
 * - Configurable via module config
 */
class Markdown
{
    /**
     * Parse Markdown text to HTML (full mode - for stories/descriptions)
     */
    public static function parse(?string $text, bool $allowImages = true): string
    {
        if (empty($text)) {
            return '';
        }

        // 🔑 Проверяем, включено ли кэширование
        $cacheEnabled = Config::getBool('content.config.cache.enabled', true);
        $cacheTtl = Config::getInt('content.config.cache.ttl', 3600);

        // Check cache
        $cacheKey = 'md_' . md5($text . ($allowImages ? '_img' : ''));
        
        if ($cacheEnabled) {
            $cached = self::getCached($cacheKey, $cacheTtl);
            if ($cached !== null) {
                return $cached;
            }
        }

        // 🔑 Используем SafeParsedown вместо стандартного Parsedown
        $parsedown = new SafeParsedown();
        
        $safeMode = Config::getBool('content.config.markdown.safe_mode', true);
        $parsedown->setSafeMode($safeMode);
        
        $markupEscaped = Config::getBool('content.config.markdown.markup_escaped', true);
        $parsedown->setMarkupEscaped($markupEscaped);
        
        $urlsLinked = Config::getBool('content.config.markdown.urls_linked', true);
        $parsedown->setUrlsLinked($urlsLinked);

        // 🔑 Отключаем изображения через наш кастомный метод
        $imagesInComments = Config::getBool('content.config.images.allowed_in_comments', false);
        if (!$allowImages || !$imagesInComments) {
            $parsedown->setImagesEnabled(false);
        }

        // Parse Markdown
        $html = $parsedown->text($text);

        // 🔑 Add custom @mentions support (если включено)
        if (Config::getBool('content.config.mentions.enabled', true)) {
            $html = self::parseMentions($html);
        }

        // Add target="_blank" and rel="noopener" to all external links
        $html = self::addLinkAttributes($html);

        // 🔑 Фильтрация по чёрному списку
        $html = self::filterBlacklist($html);

        // Cache result
        if ($cacheEnabled) {
            self::setCache($cacheKey, $html, $cacheTtl);
        }

        return $html;
    }

    /**
     * Parse Markdown for comments (restricted mode - no images)
     */
    public static function parseComment(?string $text): string
    {
        return self::parse($text, false);
    }

    /**
     * Parse plain text (no Markdown, just escape and line breaks)
     */
    public static function parsePlain(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        return '<p>' . nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')) . '</p>';
    }

    /**
     * Parse @username mentions and convert to links
     */
    private static function parseMentions(string $html): string
    {
        $minLen = Config::getInt('content.config.mentions.min_length', 3);
        $maxLen = Config::getInt('content.config.mentions.max_length', 20);
        $profileUrl = Config::getString('content.config.mentions.profile_url', '/user/{username}');
        
        $pattern = '/(?<![\w@])@([a-zA-Z0-9_]{' . $minLen . ',' . $maxLen . '})(?![\w@])/';
        
        return preg_replace_callback(
            $pattern,
            function ($matches) use ($profileUrl) {
                $username = $matches[1];
                $url = str_replace('{username}', htmlspecialchars($username), $profileUrl);
                return '<a href="' . $url . '" class="mention">@' . htmlspecialchars($username) . '</a>';
            },
            $html
        );
    }

    /**
     * Add target="_blank" and rel="noopener noreferrer" to all links
     */
    private static function addLinkAttributes(string $html): string
    {
        $externalBlank = Config::getBool('content.config.links.external_blank', true);
        $externalSecure = Config::getBool('content.config.links.external_secure', true);
        $internalDomains = Config::getArray('content.config.links.internal_domains', []);
        
        if (!$externalBlank && !$externalSecure) {
            return $html;
        }

        return preg_replace_callback(
            '/<a\s+href="([^"]+)"/',
            function ($matches) use ($externalBlank, $externalSecure, $internalDomains) {
                $url = $matches[1];
                
                $isInternal = false;
                
                if (strpos($url, '/') === 0 || strpos($url, '#') === 0) {
                    $isInternal = true;
                }
                
                foreach ($internalDomains as $domain) {
                    if (strpos($url, $domain) === 0) {
                        $isInternal = true;
                        break;
                    }
                }
                
                if ($isInternal) {
                    return $matches[0];
                }
                
                $attrs = [];
                if ($externalBlank) {
                    $attrs[] = 'target="_blank"';
                }
                if ($externalSecure) {
                    $attrs[] = 'rel="noopener noreferrer"';
                }
                
                return '<a href="' . $url . '" ' . implode(' ', $attrs);
            },
            $html
        );
    }

    /**
     * 🔑 Фильтрация по чёрному списку доменов
     */
    private static function filterBlacklist(string $html): string
    {
        $blacklist = Config::getArray('content.config.blacklist.domains', []);
        
        if (empty($blacklist)) {
            return $html;
        }
        
        return preg_replace_callback(
            '/<a\s+href="([^"]+)"[^>]*>.*?<\/a>/',
            function ($matches) use ($blacklist) {
                $url = $matches[0];
                foreach ($blacklist as $domain) {
                    if (strpos($url, $domain) !== false) {
                        return '<span class="blocked-link">[ссылка заблокирована]</span>';
                    }
                }
                return $matches[0];
            },
            $html
        );
    }

    // ==================== CACHE ====================

    private static function getCached(string $key, int $ttl = 3600): ?string
    {
        $cachePath = Config::getString('content.config.cache.path', 'cache');
        $file = dirname(__DIR__, 4) . '/storage/' . $cachePath . '/' . $key . '.html';
        
        if (file_exists($file) && (time() - filemtime($file) < $ttl)) {
            return file_get_contents($file);
        }
        return null;
    }

    private static function setCache(string $key, string $value, int $ttl): void
    {
        $cachePath = Config::getString('content.config.cache.path', 'cache');
        $cacheDir = dirname(__DIR__, 4) . '/storage/' . $cachePath . '/';
        
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        file_put_contents($cacheDir . $key . '.html', $value);
    }

    /**
     * Clear Markdown cache
     */
    public static function clearCache(): void
    {
        $cachePath = Config::getString('content.config.cache.path', 'cache');
        $cacheDir = dirname(__DIR__, 4) . '/storage/' . $cachePath . '/';
        
        $files = glob($cacheDir . 'md_*.html');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}