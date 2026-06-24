<?php

namespace App\Modules\Captcha\Core;

use App\Core\Config;

class Captcha
{
    private static string $driver = 'yandex';

    /**
     * Инициализация драйвера из конфига
     */
    public static function init(): void
    {
        self::$driver = Config::get('captcha.config.driver', 'yandex');
    }

    /**
     * 🔑 НОВЫЙ МЕТОД: Проверить, включена ли капча
     */
    public static function isEnabled(): bool
    {
        return Config::getBool('captcha.config.enabled', true);
    }

    /**
     * 🔑 НОВЫЙ МЕТОД: Нужно ли показывать капчу текущему пользователю?
     */
    public static function isRequired(): bool
    {
        // Если капча глобально отключена — не нужна
        if (!self::isEnabled()) {
            return false;
        }

        // Если пользователь авторизован и настроен пропуск — не нужна
        if (Config::getBool('captcha.config.skip_for_authorized_users', true)) {
            if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
                // Можно дополнительно проверить карму
                $minKarma = Config::getInt('captcha.config.min_karma_to_skip', 50);
                $userKarma = $_SESSION['user_karma'] ?? 0;
                if ($userKarma >= $minKarma) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Получить HTML-код капчи
     */
    public static function getHtml(): string
    {
        // 🔑 Если капча отключена — возвращаем пустую строку
        if (!self::isRequired()) {
            return '';
        }

        self::init();

        return match (self::$driver) {
            'yandex' => self::getYandexCaptcha(),
            'google' => self::getGoogleCaptcha(),
            'custom' => self::getCustomCaptcha(),
            default => throw new \Exception("Unknown captcha driver: " . self::$driver),
        };
    }

    /**
     * Проверить ответ капчи
     */
    public static function validate(?string $token = null): bool
    {
        // 🔑 Если капча отключена — всегда возвращаем true
        if (!self::isEnabled()) {
            return true;
        }

        self::init();

        return match (self::$driver) {
            'yandex' => self::validateYandex($token),
            'google' => self::validateGoogle($token),
            'custom' => self::validateCustom($token),
            default => false,
        };
    }

    /**
     * Yandex SmartCaptcha
     */
    private static function getYandexCaptcha(): string
    {
        $siteKey = Config::get('captcha.config.yandex.site_key', '');
        
        if (empty($siteKey)) {
            return '<!-- Yandex Captcha: site_key not configured -->';
        }

        return <<<HTML
<script src="https://captcha-api.yandex.ru/captcha.js" defer></script>
<div id="captcha-container" 
     class="smart-captcha" 
     data-sitekey="{$siteKey}">
</div>
HTML;
    }

    /**
     * Google reCAPTCHA
     */
    private static function getGoogleCaptcha(): string
    {
        $siteKey = Config::get('captcha.config.google.site_key', '');
        
        if (empty($siteKey)) {
            return '<!-- Google Captcha: site_key not configured -->';
        }

        return <<<HTML
<script src="https://www.google.com/recaptcha/api.js" defer></script>
<div class="g-recaptcha" data-sitekey="{$siteKey}"></div>
HTML;
    }

    /**
     * Кастомная капча
     */
    private static function getCustomCaptcha(): string
    {
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $_SESSION['captcha_answer'] = $num1 + $num2;

        return <<<HTML
<div class="custom-captcha">
    <label>Сколько будет {$num1} + {$num2}?</label>
    <input type="number" name="captcha_answer" required>
</div>
HTML;
    }

    /**
     * 🔑 Валидация Yandex с submit_url из конфига
     */
    private static function validateYandex(?string $token): bool
    {
        $token = $token ?? ($_POST['smart-token'] ?? null);
        
        if (empty($token)) {
            return false;
        }

        $secretKey = Config::get('captcha.config.yandex.secret_key', '');
        $submitUrl = Config::get('captcha.config.yandex.submit_url', 
                                  'https://smartcaptcha.cloud.yandex.ru/validate');
        
        if (empty($secretKey)) {
            return false;
        }

        // 🔑 Используем submit_url из конфига
        $ch = curl_init($submitUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'secret' => $secretKey,
            'token'  => $token,
            'ip'     => $_SERVER['REMOTE_ADDR'] ?? '',
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Таймаут 5 секунд
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Yandex Captcha validation failed with HTTP code: {$httpCode}");
            return false;
        }

        $data = json_decode($response, true);
        
        return isset($data['status']) && $data['status'] === 'ok';
    }

    /**
     * 🔑 Валидация Google с submit_url из конфига
     */
    private static function validateGoogle(?string $token): bool
    {
        $token = $token ?? ($_POST['g-recaptcha-response'] ?? null);
        
        if (empty($token)) {
            return false;
        }

        $secretKey = Config::get('captcha.config.google.secret_key', '');
        $submitUrl = Config::get('captcha.config.google.submit_url', 
                                  'https://www.google.com/recaptcha/api/siteverify');
        
        if (empty($secretKey)) {
            return false;
        }

        $ch = curl_init($submitUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'secret'   => $secretKey,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        
        return isset($data['success']) && $data['success'] === true;
    }

    /**
     * Валидация кастомной капчи
     */
    private static function validateCustom(?string $token): bool
    {
        $answer = $token ?? ($_POST['captcha_answer'] ?? null);
        
        if ($answer === null || !isset($_SESSION['captcha_answer'])) {
            return false;
        }

        $correct = $_SESSION['captcha_answer'];
        unset($_SESSION['captcha_answer']);

        return (int)$answer === (int)$correct;
    }
}