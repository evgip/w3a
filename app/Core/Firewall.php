<?php

namespace App\Core;

class Firewall
{
    /**
     * Intercept incoming connections and check against the database IP blacklists matrix
     */
    public static function check(): void
    {
        $ip = self::getRealIp();
        $db = Database::getConnection();

        // High-performance single lookup statement index scan
        $stmt = $db->prepare("SELECT `reason` FROM `banned_ips` WHERE `ip_address` = :ip LIMIT 1");
        $stmt->execute(['ip' => $ip]);
        $reason = $stmt->fetchColumn();

        if ($reason !== false) {
            http_response_code(403); // Forbidden
            echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title>403 Access Denied</title>';
            echo '<link rel="stylesheet" href="/css/app.min.css"></head><body class="blocked-error-body">';
            echo '<div class="profile-container blocked-error-card">';
            echo '<h2 class="blocked-error-title">⛔ Ваш IP-адрес принудительно заблокирован</h2>';
            echo '<p class="admin-subtitle-desc">Доступ к платформе ограничен администрацией сайта.</p>';
            echo '<div class="profile-bio-quote-box"><strong>Причина блокировки:</strong> ' . e($reason) . '</div>';
            echo '<p class="field-sub-hint">Если вы считаете, что это произошло по ошибке, свяжитесь с поддержкой.</p>';
            echo '</div></body></html>';
            exit;
        }
    }
	
	public static function getRealIp(): string
	{
		// Если сайт за Cloudflare
		if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
			return $_SERVER['HTTP_CF_CONNECTING_IP'];
		}
		// Если сайт за другим прокси
		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			// X-Forwarded-For может содержать цепочку: "client, proxy1, proxy2"
			$ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
			return trim($ips[0]);
		}
		
		return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
	}
}
