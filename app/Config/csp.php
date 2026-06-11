<?php

return [
    // Разрешаем выполнение внешних скриптов капчи

    'default_src' => [
         'https://smartcaptcha.cloud.yandex.ru'
    ],

    'script_src' => [
        'https://google.com',
        'https://gstatic.com',
        'https://smartcaptcha.cloud.yandex.ru'
    ],
    
    'style_src' => [
        'https://googleapis.com',
		'https://smartcaptcha.cloud.yandex.ru',
    ],
    
    'font_src' => [
        'https://gstatic.com',
		'https://smartcaptcha.cloud.yandex.ru',
    ],
    
    // Разрешаем загрузку картинок-пазлов капчи
    'img_src' => [
        'https://smartcaptcha.cloud.yandex.ru',
        'https://google.com'
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