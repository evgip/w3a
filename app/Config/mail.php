<?php

/**
 * Enterprise SMTP Mailer Engine Configuration parameters
 */
return [
    'host'       => 'smtp.yandex.ru',        // e.g., ://gmail.com or smtp.yandex.ru
    'auth'       => true,
    'username'   => 'your-login@yandex.ru',  // Your production mailbox username string
    'password'   => 'your-app-password',     // App-specific password generated inside email settings
    'encryption' => 'tls',                   // 'tls' (Port 587) or 'ssl' (Port 465)
    'port'       => 587,
    
    'from_email' => 'your-login@yandex.ru',
    'from_name'  => 'MyShare Community'
];
