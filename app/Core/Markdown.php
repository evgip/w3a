<?php

namespace App\Core;

/**
 * Markdown parser using Parsedown library
 * 
 * Features:
 * - Full Markdown support via Parsedown
 * - XSS protection via SafeMode
 * - Custom @mentions support
 * - File-based caching for performance
 * - Two modes: full (posts) and restricted (comments)
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

        // Check cache
        $cacheKey = 'md_' . md5($text . ($allowImages ? '_img' : ''));
        $cached = self::getCached($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Initialize Parsedown with SafeMode for XSS protection
        $parsedown = new \Parsedown();
        $parsedown->setSafeMode(true);

        // Disable images if not allowed (for comments)
        if (!$allowImages) {
            $parsedown->setImagesEnabled(false);
        }

        // Parse Markdown
        $html = $parsedown->text($text);

        // Add custom @mentions support (Parsedown doesn't handle this)
        //  $html = self::parseMentions($html);

        // Add target="_blank" and rel="noopener" to all external links
        $html = self::addLinkAttributes($html);

        // Cache result (1 hour)
        self::setCache($cacheKey, $html, 3600);

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
     * This runs AFTER Parsedown to avoid conflicts
     */
    private static function parseMentions(string $html): string
    {
        // Match @username (3-20 chars, alphanumeric + underscore)
        // But not inside <code>, <pre>, or <a> tags
        return preg_replace_callback(
            '/(?<![\w@])@([a-zA-Z0-9_]{3,20})(?![\w@])/',
            function ($matches) {
                $username = $matches[1];
                return '<a href="/user/' . htmlspecialchars($username) . '" class="mention">@' . htmlspecialchars($username) . '</a>';
            },
            $html
        );
    }

    /**
     * Add target="_blank" and rel="noopener noreferrer" to all links
     */
    private static function addLinkAttributes(string $html): string
    {
        return preg_replace_callback(
            '/<a\s+href="([^"]+)"/',
            function ($matches) {
                $url = $matches[1];
                // Don't add target="_blank" for internal links
                if (strpos($url, '/') === 0 || strpos($url, '#') === 0) {
                    return $matches[0];
                }
                return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer"';
            },
            $html
        );
    }

    // ==================== CACHE ====================

    private static function getCached(string $key): ?string
    {
        $file = dirname(__DIR__) . '/../storage/cache/' . $key . '.html';
        if (file_exists($file) && (time() - filemtime($file) < 3600)) {
            return file_get_contents($file);
        }
        return null;
    }

    private static function setCache(string $key, string $value, int $ttl): void
    {
        $cacheDir = dirname(__DIR__) . '/../storage/cache/';
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
        $cacheDir = dirname(__DIR__) . '/../storage/cache/';
        $files = glob($cacheDir . 'md_*.html');
        foreach ($files as $file) {
            unlink($file);
        }
    }
}
