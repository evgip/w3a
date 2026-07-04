<?php

return [
    'app' => [
        'name' => 'w3a',
        'env' => env('APP_ENV', 'development'),
        'url' => env('APP_URL', 'http://localhost'),
        'lang' => env('APP_LANG', 'ru'),
		
		// === Система приглашений === 
		// Убрать потом в МОдуль
        'invitations_enabled' => false,              // Включить/выключить систему инвайтов
        'min_karma_for_invitation' => 10,            // Минимальная карма для создания приглашений
        'max_invitations_per_user' => 5,             // Максимум активных приглашений на пользователя
        'invitation_expires_days' => 7,              // Срок действия приглашения в днях
		
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