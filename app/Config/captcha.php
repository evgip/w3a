<?php

/**
 * Конфигурация модулей Капчи (Google reCAPTCHA / Yandex SmartCaptcha)
 */
return [
    // Активный движок: 'google', 'yandex' или null (чтобы отключить капчу)
    'driver' => 'google', 

    'drivers' => [
        'google' => [
            'site_key'   => 'YOUR_GOOGLE_SITE_KEY',
            'secret_key' => 'YOUR_GOOGLE_SECRET_KEY',
            'submit_url' => 'https://google.com'
        ],
        'yandex' => [
            'site_key'   => 'YOUR_YANDEX_SITE_KEY',
            'secret_key' => 'YOUR_YANDEX_SECRET_KEY',
            'submit_url' => 'https://yandexcloud.net'
        ]
    ]
];
