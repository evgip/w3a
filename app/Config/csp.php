<?php

return [
    // ═══════════════════════════════════════════
    // 🔐 YANDEX SMARTCAPTCHA
    // ═══════════════════════════════════════════
    
    'default_src' => [
        'https://captcha-api.yandex.ru',
        'https://smartcaptcha.yandex.ru',
        'https://smartcaptcha.cloud.yandex.ru',
    ],

    'script_src' => [
        'https://captcha-api.yandex.ru',           // ← JS капчи
        'https://smartcaptcha.yandex.ru',          // ← Iframe капчи
        'https://www.google.com',
        'https://www.gstatic.com',
    ],
    
    'style_src' => [
        'https://smartcaptcha.yandex.ru',
        'https://fonts.googleapis.com',
    ],
    
    'font_src' => [
        'https://smartcaptcha.yandex.ru',
        'https://fonts.gstatic.com',
    ],
    
    'img_src' => [
        'https://smartcaptcha.yandex.ru',
        'https://www.google.com',
        'https://mc.yandex.ru',                    // ← Метрика (если нужна)
    ],
    
    // 🔑 НОВОЕ: frame-src для iframe виджетов
    'frame_src' => [
        'https://captcha-api.yandex.ru',           // ← ← ← ДОБАВЛЕНО!
        'https://smartcaptcha.yandex.ru',
        'https://www.google.com',
        'https://recaptcha.google.com',
    ],
    
    // 🔑 НОВОЕ: connect-src для API
    'connect_src' => [
        'https://captcha-api.yandex.ru',           // ← ← ← ДОБАВЛЕНО!
        'https://smartcaptcha.cloud.yandex.ru',
        'https://www.google.com',
    ],
    
    'frame_ancestors' => [
        'none' 
    ],
    
    'hsts' => [
        'enabled' => true,
        'max_age' => 31536000,
        'include_subdomains' => true,
        'preload' => true
    ]
];