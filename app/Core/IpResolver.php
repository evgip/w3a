<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Единый сервис для определения реального IP-адреса клиента.
 * Доверяет proxy-заголовкам только когда запрос пришёл от доверенного proxy.
 */
class IpResolver
{
    /**
     * @var array Список доверенных proxy-серверов
     */
    private array $trustedProxies;

    /**
     * @var bool Использовать ли диапазоны Cloudflare по умолчанию
     */
    private bool $useCloudflareDefaults;

    /**
     * Конструктор с инъекцией конфигурации.
     *
     * @param array $trustedProxies Список доверенных proxy (CIDR нотация)
     * @param bool $useCloudflareDefaults Использовать диапазоны Cloudflare, если список пуст
     */
    public function __construct(array $trustedProxies = [], bool $useCloudflareDefaults = true)
    {
        // ✅ Убрали config() helper - теперь полностью зависим от DI
        $this->trustedProxies = $trustedProxies;
        $this->useCloudflareDefaults = $useCloudflareDefaults;
    }

    /**
     * Получить реальный IP клиента
     *
     * @return string IP-адрес клиента
     */
    public function getClientIp(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Проверяем, пришёл ли запрос от доверенного proxy
        if ($this->isTrustedProxy($remoteAddr)) {
            // Cloudflare
            if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
                $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }

            // Другие proxy
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
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

        return $remoteAddr;
    }

    /**
     * Проверить, является ли IP доверенным proxy
     */
    public function isTrustedProxy(string $ip): bool
    {
        $trustedProxies = $this->trustedProxies;

        if (empty($trustedProxies) && $this->useCloudflareDefaults) {
            $trustedProxies = $this->getCloudflareIps();
        }

        if (empty($trustedProxies)) {
            return false;
        }

        foreach ($trustedProxies as $cidr) {
            if ($this->ipInCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Получить диапазоны IP Cloudflare
     */
    public function getCloudflareIps(): array
    {
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
    public function ipInCidr(string $ip, string $cidr): bool
    {
        list($subnet, $bits) = explode('/', $cidr);

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

    /**
     * Установить список доверенных proxy
     */
    public function setTrustedProxies(array $trustedProxies): self
    {
        $this->trustedProxies = $trustedProxies;
        return $this;
    }

    /**
     * Получить список доверенных proxy
     */
    public function getTrustedProxies(): array
    {
        return $this->trustedProxies;
    }
}
