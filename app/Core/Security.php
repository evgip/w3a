<?php

namespace App\Core;

class Security
{
    private static ?string $nonce = null;

    public static function getNonce(): string
    {
        if (self::$nonce === null) {
            self::$nonce = bin2hex(random_bytes(16));
        }
        return self::$nonce;
    }

    public static function sendCspHeader(): void
    {
        if (headers_sent()) {
            Logger::error("Security Layer Failure: Headers already dispatched.");
            return;
        }

        $nonce = self::getNonce();

        $configFile = dirname(__DIR__) . '/Config/csp.php';
        $cspConfig = file_exists($configFile) ? require $configFile : [];

        $mergeOrigins = function (string $key, array $defaults = []) use ($cspConfig): string {
            $configured = $cspConfig[$key] ?? [];
            $allOrigins = array_unique(array_merge($defaults, $configured));
            return implode(' ', $allOrigins);
        };

        $policy = [
            "default-src 'self' " . $mergeOrigins('default_src'),
            "script-src 'self' 'nonce-{$nonce}' " . $mergeOrigins('script_src'),
            "style-src-elem 'self' 'unsafe-inline' " . $mergeOrigins('style_src'),
            "style-src-attr 'self' 'unsafe-inline'",

            "frame-src 'self' " . $mergeOrigins('frame_src'),
            
            "connect-src 'self' " . $mergeOrigins('connect_src'),
            
            "img-src 'self' data: " . $mergeOrigins('img_src'),
            "font-src 'self' " . $mergeOrigins('font_src'),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        $frameAncestors = $cspConfig['frame_ancestors'] ?? ['none'];
        if (in_array('none', $frameAncestors)) {
            $policy[] = "frame-ancestors 'none'";
        } else {
            $policy[] = "frame-ancestors 'self' " . implode(' ', $frameAncestors);
        }

        header("Content-Security-Policy: " . implode("; ", $policy));

        header("X-Content-Type-Options: nosniff");
        header('X-Frame-Options: SAMEORIGIN');
        header("X-XSS-Protection: 1; mode=block");
        header("Referrer-Policy: strict-origin-when-cross-origin");

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
