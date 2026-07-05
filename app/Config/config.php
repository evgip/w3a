<?php

return [
    'app' => [
        'name' => 'w3a',
        'env' => env('APP_ENV', 'development'),
        'url' => env('APP_URL', 'http://localhost'),
        'lang' => env('APP_LANG', 'ru'),
		
		// Доверенные proxy (если используете свой proxy, добавьте его IP/CIDR)
		// Если используете Cloudflare, оставьте пустым массивом — будут использоваться встроенные диапазоны
		'trusted_proxies' => [],
    ],
    
    'database' => [
        'host' => env('DB_HOST', 'MySQL-8.2'),
        'port' => env('DB_PORT', '3306'),
        'dbname' => env('DB_NAME', 'soc'),
        'username' => env('DB_USER', 'root'),
        'password' => env('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ]

];