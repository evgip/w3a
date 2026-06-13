<?php

namespace App\Core;

/**
 * Markdown parser with security, caching, and extensibility
 * 
 * Supports: headings, bold, italic, code (inline & block), links, images,
 * lists, blockquotes, horizontal rules, mentions (@user), auto-links
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

        // 1. XSS protection
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // 2. Code blocks (```code```) - must be first to prevent inner parsing
        $text = self::parseCodeBlocks($text);

        // 3. Headings (# ## ###)
        $text = self::parseHeadings($text);

        // 4. Horizontal rules (---, ***, ___)
        $text = self::parseHorizontalRules($text);

        // 5. Blockquotes (>)
        $text = self::parseBlockquotes($text);

        // 6. Lists (unordered and ordered)
        $text = self::parseLists($text);

        // 7. Images ![alt](url)
        if ($allowImages) {
            $text = self::parseImages($text);
        }

        // 8. Links [text](url)
        $text = self::parseLinks($text);

        // 9. Auto-links (bare URLs)
        $text = self::parseAutoLinks($text);

        // 10. Mentions (@username)
        $text = self::parseMentions($text);

        // 11. Bold (**text** or __text__)
        $text = self::parseBold($text);

        // 12. Italic (*text* or _text_)
        $text = self::parseItalic($text);

        // 13. Inline code (`code`)
        $text = self::parseInlineCode($text);

        // 14. Paragraphs
        $text = self::parseParagraphs($text);

        // Cache result
        self::setCache($cacheKey, $text, 3600);

        return $text;
    }

    /**
     * Parse Markdown for comments (restricted mode - no images, limited features)
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

    // ==================== PARSERS ====================

    private static function parseCodeBlocks(string $text): string
    {
        // Fenced code blocks with language: ```lang\ncode\n```
        $text = preg_replace_callback('/```(\w*)\n(.*?)```/s', function ($matches) {
            $lang = !empty($matches[1]) ? ' class="language-' . $matches[1] . '"' : '';
            $code = $matches[2];
            return '<pre><code' . $lang . '>' . $code . '</code></pre>';
        }, $text);

        // Fenced code blocks without language: ```\ncode\n```
        $text = preg_replace_callback('/```\n(.*?)```/s', function ($matches) {
            return '<pre><code>' . $matches[1] . '</code></pre>';
        }, $text);

        return $text;
    }

    private static function parseHeadings(string $text): string
    {
        $text = preg_replace('/^###### (.*?)$/m', '<h6>$1</h6>', $text);
        $text = preg_replace('/^##### (.*?)$/m', '<h5>$1</h5>', $text);
        $text = preg_replace('/^#### (.*?)$/m', '<h4>$1</h4>', $text);
        $text = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $text);
        $text = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $text);
        $text = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $text);
        return $text;
    }

    private static function parseHorizontalRules(string $text): string
    {
        $text = preg_replace('/^(\*{3,}|-{3,}|_{3,})$/m', '<hr>', $text);
        return $text;
    }

    private static function parseBlockquotes(string $text): string
    {
        $text = preg_replace_callback('/^> (.*?)$/m', function ($matches) {
            return '<blockquote>' . $matches[1] . '</blockquote>';
        }, $text);

        // Merge consecutive blockquotes
        $text = preg_replace('/<\/blockquote>\s*<blockquote>/', "\n", $text);
        
        return $text;
    }

    private static function parseLists(string $text): string
    {
        // Unordered lists (- or * or +)
        $text = preg_replace_callback('/(?:^[-*+] .+\n?)+/m', function ($matches) {
            $items = preg_split('/^[-*+] /m', trim($matches[0]), -1, PREG_SPLIT_NO_EMPTY);
            $html = '<ul>';
            foreach ($items as $item) {
                $html .= '<li>' . trim($item) . '</li>';
            }
            $html .= '</ul>';
            return $html;
        }, $text);

        // Ordered lists (1. 2. 3.)
        $text = preg_replace_callback('/(?:^\d+\. .+\n?)+/m', function ($matches) {
            $items = preg_split('/^\d+\. /m', trim($matches[0]), -1, PREG_SPLIT_NO_EMPTY);
            $html = '<ol>';
            foreach ($items as $item) {
                $html .= '<li>' . trim($item) . '</li>';
            }
            $html .= '</ol>';
            return $html;
        }, $text);

        return $text;
    }

    private static function parseImages(string $text): string
    {
        // ![alt](url) - only allow http/https
        $text = preg_replace_callback('/!\[(.*?)\]\((https?:\/\/[^\)]+)\)/', function ($matches) {
            $alt = $matches[1];
            $url = $matches[2];
            return '<img src="' . $url . '" alt="' . $alt . '" loading="lazy" class="markdown-img">';
        }, $text);
        return $text;
    }

    private static function parseLinks(string $text): string
    {
        // [text](url) - only allow http/https
        $text = preg_replace_callback('/\[(.*?)\]\((https?:\/\/[^\)]+)\)/', function ($matches) {
            $text = $matches[1];
            $url = $matches[2];
            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $text . '</a>';
        }, $text);
        return $text;
    }

    private static function parseAutoLinks(string $text): string
    {
        // Convert bare URLs to links
        $text = preg_replace_callback(
            '/(?<!="|\'|>)(https?:\/\/[^\s<]+)/',
            function ($matches) {
                $url = $matches[1];
                // Don't wrap if already inside an <a> tag
                return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>';
            },
            $text
        );
        return $text;
    }

    private static function parseMentions(string $text): string
    {
        // @username - link to user profile
        $text = preg_replace_callback('/@([a-zA-Z0-9_]{3,20})/', function ($matches) {
            $username = $matches[1];
            return '<a href="/user/' . $username . '" class="mention">@' . $username . '</a>';
        }, $text);
        return $text;
    }

    private static function parseBold(string $text): string
    {
        // **text** or __text__
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text);
        return $text;
    }

    private static function parseItalic(string $text): string
    {
        // *text* or _text_ (but not inside words for _)
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/(?<!\w)_(.+?)_(?!\w)/', '<em>$1</em>', $text);
        return $text;
    }

    private static function parseInlineCode(string $text): string
    {
        // `code`
        $text = preg_replace('/`(.+?)`/', '<code>$1</code>', $text);
        return $text;
    }

    private static function parseParagraphs(string $text): string
    {
        $text = trim($text);
        
        // Split by double newlines
        $blocks = preg_split('/\n{2,}/', $text);
        $result = [];

        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block)) continue;

            // Don't wrap if it's already a block element
            if (preg_match('/^<(h[1-6]|ul|ol|li|blockquote|pre|hr|div|table)/', $block)) {
                $result[] = $block;
            } else {
                // Wrap in <p> and convert single newlines to <br>
                $result[] = '<p>' . nl2br($block) . '</p>';
            }
        }

        return implode("\n", $result);
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