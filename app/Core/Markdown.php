<?php

namespace App\Core;

class Markdown
{
    /**
     * Converts a raw unsafe Markdown string into fully clean, secure semantic HTML markup
     * 
     * @param string|null $text
     * @return string
     */
    public static function parse(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // 1. Defend against XSS by sanitizing all incoming characters first
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // 2. Format standard Markdown headings (# Heading, ## Heading)
        $text = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $text);
        $text = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $text);
        $text = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $text);

        // 3. Format bold text (**text**) and italic text (*text*)
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*([^\*]+)\*/', '<em>$1</em>', $text);

        // 4. Format inline code fragments (`code`)
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);

        // 5. Format standard text link anchors ([Text](URL)) safely
        // Strictly allow only absolute http/https schemas to prevent javascript pseudo-protocol links
        $text = preg_replace_callback('/\[(.*?)\]\((https?:\/\/.*?)\)/', function ($matches) {
            return '<a href="' . $matches[2] . '" class="story-title-link" target="_blank" rel="noopener noreferrer">' . $matches[1] . '</a>';
        }, $text);

        // 6. Format line breaks into regular paragraph blocks cleanly
        $text = trim($text);
        $paragraphs = explode("\n\n", $text);
        foreach ($paragraphs as &$p) {
            if (strpos($p, '<h') === false) {
                // If it's not a heading element block, wrap line breaks with standard tags
                $p = '<p>' . nl2br($p) . '</p>';
            }
        }

        return implode("\n", $paragraphs);
    }
}
