<?php

namespace App\Core;

class Security
{
    private static ?string $nonce = null;

    /**
     * Generates a single cryptographically secure token per request
	 * <script nonce="<?= \App\Core\Security::getNonce(); ?>">
     */
    public static function getNonce(): string
    {
        if (self::$nonce === null) {
            self::$nonce = bin2hex(random_bytes(16));
        }
        return self::$nonce;
    }

    /**
     * Sends the Content Security Policy header to the browser
     * Compiles dynamic whitelists and dispatches comprehensive security defense headers
     */
    public static function sendCspHeader(): void
    {
        // 1. Defend against HTTP Header Injection / Split exploits
        if (headers_sent()) {
            Logger::error("Security Layer Failure: Headers already dispatched. Defensive envelopes bypassed.");
            return;
        }

        $nonce = self::getNonce();

        // Load the detached infrastructure parameter array file safely
        $configFile = dirname(__DIR__) . '/Config/csp.php';
        $cspConfig = file_exists($configFile) ? require $configFile : [];

        // Helper closure to safely aggregate and sanitize config parameters into clean inline strings
        $mergeOrigins = function (string $key, array $defaults = []) use ($cspConfig): string {
            $configured = $cspConfig[$key] ?? [];
            $allOrigins = array_unique(array_merge($defaults, $configured));
            return implode(' ', $allOrigins);
        };

        // 2. Compile Strict Environment CSP Directives Blueprint 
        $policy = [
            "default-src 'self' "  . $mergeOrigins('default_src'), // Halt all communication vectors by default unless whitelisted explicitly
            "script-src 'self' 'nonce-{$nonce}' " . $mergeOrigins('script_src'),

            // РАЗДЕЛЯЕМ ДИРЕКТИВУ СТИЛЕЙ НА ДВЕ:
            // 1. Для тегов <style> и файлов <link> (разрешаем 'unsafe-inline' Яндексу и Google без конфликта с nonce)
            "style-src-elem 'self' 'unsafe-inline' " . $mergeOrigins('style_src'),
            // 2. Для инлайновых атрибутов элементов style="..."
            "style-src-attr 'self' 'unsafe-inline'",


            "img-src 'self' data: " . $mergeOrigins('img_src'),
            "font-src 'self' " . $mergeOrigins('font_src'),
            "object-src 'none'",   // Turn off Flash, Java Applets, and unsafe legacy runtime environments
            "base-uri 'self'",     // Restrict <base> HTML targeting injections
            "form-action 'self'",  // Mitigate form credential harvesting hijack rerouting vectors
        ];

        // Process clickjacking rules dynamically from configurations mapping
        $frameAncestors = $cspConfig['frame_ancestors'] ?? ['none'];
        if (in_array('none', $frameAncestors)) {
            $policy[] = "frame-ancestors 'none'";
        } else {
            $policy[] = "frame-ancestors 'self' " . implode(' ', $frameAncestors);
        }

        // Dispatch Content-Security-Policy to client window instances
        header("Content-Security-Policy: " . implode("; ", $policy));

        // 3. AUTOMATED ADDED EXPLOIT GUARD PROTECTION TIERS

        // Mitigation tool against MIME-Type spoofing attacks (Forces browser compliance with declared types)
        header("X-Content-Type-Options: nosniff");

        // Clickjacking safeguard covering legacy browser clients that do not parse 'frame-ancestors' natively
        header("X-Frame-Options: DENY");

        // XSS Protection fallback protocol trigger for older browsers
        header("X-XSS-Protection: 1; mode=block");

        // Referrer-Policy restriction layer (Shields tracking string credentials when users click outgoing links)
        header("Referrer-Policy: strict-origin-when-cross-origin");

        // 4. STRICT DYNAMIC HSTS ENFORCEMENT ENGINE
        $hsts = $cspConfig['hsts'] ?? [];
        if ($hsts['enabled'] ?? false) {
            $hstsHeader = "max-age=" . (int)($hsts['max_age'] ?? 31536000);
            if ($hsts['include_subdomains'] ?? true) {
                $hstsHeader .= "; includeSubDomains";
            }
            if ($hsts['preload'] ?? true) {
                $hstsHeader .= "; preload";
            }
            header("Strict-Transport-Security: " . $hstsHeader);
        }
    }
}
