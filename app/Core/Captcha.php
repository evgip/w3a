<?php

namespace App\Core;

class Captcha
{
    /**
     * Dynamically generates a captcha HTML code based on the selected driver
     * Scripts are automatically signed with the current nonce to bypass CSP
     * 
     * Динамически генерирует HTML-код капчи на основе выбранного драйвера
     * Скрипты автоматически подписываются текущим nonce для обхода CSP
     *
     * @return string
     */

    public static function render(): string
    {
        $configFile = dirname(__DIR__) . '/Config/captcha.php';
        $config = file_exists($configFile) ? require $configFile : [];
        $driver = $config['driver'] ?? null;

        if (!$driver || !isset($config['drivers'][$driver])) {
            return '<!-- Капча отключена в конфигурации -->';
        }

        $driverConfig = $config['drivers'][$driver];
        $siteKey = htmlspecialchars($driverConfig['site_key'], ENT_QUOTES, 'UTF-8');
        $nonce = \App\Core\Security::getNonce();

        if ($driver === 'yandex') {
            return '
                <script src="https://smartcaptcha.cloud.yandex.ru/captcha.js" defer nonce="' . $nonce . '"></script>
                <div id="captcha-container" class="smart-captcha" data-sitekey="' . $siteKey . '"></div>
                <br>
            ';
        }

        // Дефолтный фолбэк на Google reCAPTCHA v2
        return '
            <script src="https://google.com" async defer nonce="' . $nonce . '"></head></script>
            <div class="g-recaptcha" data-sitekey="' . $siteKey . '"></div>
            <br>
        ';
    }

    /**
     * Серверная валидация токена капчи через cURL
     */
    public static function verify(): bool
    {
        $configFile = dirname(__DIR__) . '/Config/captcha.php';
        $config = file_exists($configFile) ? require $configFile : [];
        $driver = $config['driver'] ?? null;

        if (!$driver) {
            return true; // Капча отключена, валидация пройдена автоматически
        }

        $driverConfig = $config['drivers'][$driver];
        $secretKey = $driverConfig['secret_key'];
        $submitUrl = $driverConfig['submit_url'];

        // Перехватываем токен ответа в зависимости от выбранного провайдера
        $token = ($driver === 'yandex')
            ? ($_POST['smart-token'] ?? '')
            : ($_POST['g-recaptcha-response'] ?? '');

        if (empty($token)) {
            return false;
        }

        // Выполняем безопасный cURL-запрос к API провайдера
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $submitUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);

        // Яндекс ожидает параметры в формате query-строки или POST, Google — стандартный POST
        $fields = [
            'secret'   => $secretKey,
            'response' => $token,
            'ip'       => $_SERVER['REMOTE_ADDR'] ?? ''
        ];

        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            Logger::error("Ошибка сетевого соединения с сервером валидации капчи: {$driver}");
            return false;
        }

        $result = json_decode($response, true);

        // У Яндекса и Гугла успешный статус возвращается в ключе 'status' или 'success'
        if ($driver === 'yandex') {
            return isset($result['status']) && $result['status'] === 'ok';
        }

        return isset($result['success']) && $result['success'] === true;
    }
}
