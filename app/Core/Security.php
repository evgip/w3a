<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Сервис безопасности: nonce для CSP и заголовки безопасности.
 * 
 * Отвечает за:
 * - Генерацию nonce для Content Security Policy
 * - Отправку HTTP-заголовков безопасности
 * 
 * НЕ отвечает за CSRF (это делает Request.php)
 */
class Security
{
    private ?string $nonce = null;
    private Logger $logger;

    /**
     * Конструктор с инъекцией Logger
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Получить nonce для CSP
     */
    public function getNonce(): string
    {
        if ($this->nonce === null) {
            $this->nonce = bin2hex(random_bytes(16));
        }
        return $this->nonce;
    }

    /**
     * Отправить заголовки CSP и безопасности
     */
    public function sendCspHeader(): void
    {
        if (headers_sent()) {
            $this->logger->error("Security Layer Failure: Headers already dispatched.");
            return;
        }

        $nonce = $this->getNonce();

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