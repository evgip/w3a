<?php

return [
    'app' => [
        'name' => 'My Forum',
        'env' => 'development', // development или production
		'url' => 'http://soc.local',
		'lang' => 'ru', // Язык по умолчанию
		'min_karma_for_downvote' => 10,
		
        // === Система приглашений ===
        'invitations_enabled' => false,              // Включить/выключить систему инвайтов
        'min_karma_for_invitation' => 10,            // Минимальная карма для создания приглашений
        'max_invitations_per_user' => 5,             // Максимум активных приглашений на пользователя
        'invitation_expires_days' => 7,              // Срок действия приглашения в днях
    ],
    'database' => [
        'host' => 'MySQL-8.2',
        'port' => '3306',
        'dbname' => 'soc',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ]
];
