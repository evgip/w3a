<?php

/**
 * Конфигурация модуля Mail
 * 
 * Настройки отправки email через PHPMailer (SMTP)
 * Все параметры читаются через Config::get('mail.config.xxx')
 * 
 * Примеры использования:
 *   Config::get('mail.config.host')          // 'smtp.yandex.ru'
 *   Config::get('mail.config.username')      // 'noreply@site.com'
 *   Config::get('mail.config.from_email')    // 'noreply@site.com'
 */

return [
    // ═══════════════════════════════════════════
    // 🔑 SMTP НАСТРОЙКИ
    // ═══════════════════════════════════════════
    
    // SMTP сервер
    'host' => env('MAIL_HOST', 'smtp.yandex.ru'),
    
    // Порт SMTP
    'port' => env('MAIL_PORT', 465),
    
    // Включить SMTP аутентификацию
    'auth' => env('MAIL_AUTH', true),
    
    // Логин SMTP
    'username' => env('MAIL_USERNAME', 'your-login@yandex.ru'),
    
    // Пароль SMTP
    'password' => env('MAIL_PASSWORD', ''),
    
    // Шифрование: 'tls', 'ssl' или пустая строка
    // Для port 465 используйте 'ssl' (PHPMailer::ENCRYPTION_SMTPS)
    // Для port 587 используйте 'tls' (PHPMailer::ENCRYPTION_STARTTLS)
    'encryption' => env('MAIL_ENCRYPTION', 'ssl'),

    // ═══════════════════════════════════════════
    // 👤 ОТПРАВИТЕЛЬ ПО УМОЛЧАНИЮ
    // ═══════════════════════════════════════════
    
    // Email отправителя
    'from_email' => env('MAIL_FROM_ADDRESS', 'noreply@soc.local'),
    
    // Имя отправителя
    'from_name' => env('MAIL_FROM_NAME', 'w3a'),

    // ═══════════════════════════════════════════
    // 🧪 РЕЖИМ ТЕСТИРОВАНИЯ
    // ═══════════════════════════════════════════
    
    // Если true — письма не отправляются, а пишутся в лог
    // Полезно для development и staging
    'pretend' => env('MAIL_PRETEND', false),
    
    // Отладочный режим PHPMailer (SMTP::DEBUG_SERVER и т.д.)
    // 0 = выключено, 1-4 = уровни детализации
    'debug' => env('MAIL_DEBUG', 0),
];