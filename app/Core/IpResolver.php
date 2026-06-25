<?php

namespace App\Core;

/**
 * Единый сервис для определения реального IP-адреса клиента.
 * Доверяет proxy-заголовкам только когда запрос пришёл от доверенного proxy.
 */
class IpResolver
{
    /**
     * Получить реальный IP клиента
     */
    public static function getClientIp(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        // Проверяем, пришёл ли запрос от доверенного proxy
        if (self::isTrustedProxy($remoteAddr)) {
            // Cloudflare
            if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
                $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
            
            // Другие proxy
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                // X-Forwarded-For может содержать цепочку: "client, proxy1, proxy2"
                // Берём первый IP (оригинальный клиент)
                $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
            
            // Другие заголовки proxy
            if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
                $ip = $_SERVER['HTTP_X_REAL_IP'];
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        // Если не от доверенного proxy или нет proxy-заголовков, используем REMOTE_ADDR
        return $remoteAddr;
    }
    
    /**
     * Проверить, является ли IP доверенным proxy
     */
    private static function isTrustedProxy(string $ip): bool
    {
        // Получаем список доверенных proxy из конфига
        $trustedProxies = config('config.app.trusted_proxies', [], 'array');
        
        // Если список пуст, можно использовать встроенные диапазоны Cloudflare
        if (empty($trustedProxies)) {
            $trustedProxies = self::getCloudflareIps();
        }
        
        // Проверяем, входит ли IP в один из доверенных диапазонов
        foreach ($trustedProxies as $cidr) {
            if (self::ipInCidr($ip, $cidr)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Получить диапазоны IP Cloudflare
     * В продакшене лучше кешировать эти данные
     */
    private static function getCloudflareIps(): array
    {
        // Статические диапазоны Cloudflare (обновляйте периодически)
        // https://www.cloudflare.com/ips-v4
        // https://www.cloudflare.com/ips-v6
        return [
            '173.245.48.0/20',
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '141.101.64.0/18',
            '108.162.192.0/18',
            '190.93.240.0/20',
            '188.114.96.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17',
            '162.158.0.0/15',
            '104.16.0.0/13',
            '104.24.0.0/14',
            '172.64.0.0/13',
            '131.0.72.0/22',
            // IPv6 диапазоны
            '2400:cb00::/32',
            '2606:4700::/32',
            '2803:f800::/32',
            '2405:b500::/32',
            '2405:8100::/32',
            '2a06:98c0::/29',
            '2c0f:f248::/32',
        ];
    }
    
    /**
     * Проверить, входит ли IP в CIDR диапазон
     */
    private static function ipInCidr(string $ip, string $cidr): bool
    {
        list($subnet, $bits) = explode('/', $cidr);
        
        // Поддержка IPv4 и IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip = ip2long($ip);
            $subnet = ip2long($subnet);
            
            if ($ip === false || $subnet === false) {
                return false;
            }
            
            $mask = -1 << (32 - (int)$bits);
            $subnet &= $mask;
            
            return ($ip & $mask) === $subnet;
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // Для IPv6 используем более сложную логику
            $ip = inet_pton($ip);
            $subnet = inet_pton($subnet);
            
            if ($ip === false || $subnet === false) {
                return false;
            }
            
            $ip = unpack('A16', $ip)[1];
            $subnet = unpack('A16', $subnet)[1];
            
            $fullMask = str_repeat("\xff", (int)($bits / 8));
            if ($bits % 8 !== 0) {
                $fullMask .= chr(~(0xff >> ($bits % 8)));
            }
            $fullMask = str_pad($fullMask, 16, "\x00");
            
            return ($ip & $fullMask) === ($subnet & $fullMask);
        }
        
        return false;
    }
}