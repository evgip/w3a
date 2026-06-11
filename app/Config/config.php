<?php

return [
    'app' => [
        'name' => 'My Forum',
        'env' => 'development', // development или production
		'url' => 'http://soc.local',
		'lang' => 'ru', // Язык по умолчанию
		'min_karma_for_downvote' => 10
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
