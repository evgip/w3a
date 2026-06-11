<?php

return [
    // Разрешаем выполнение внешних скриптов капчи
    'script_src' => [
        'https://google.com',
        'https://gstatic.com',
        'https://yandexcloud.net'
    ],
    
    'style_src' => [
        'https://googleapis.com'
    ],
    
    'font_src' => [
        'https://gstatic.com'
    ],
    
    // Разрешаем загрузку картинок-пазлов капчи
    'img_src' => [
        'https://yandexcloud.net',
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