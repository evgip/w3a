# Модуль Captcha

Модуль для работы с капчей (Yandex SmartCaptcha, Google reCAPTCHA, кастомная капча).

## Установка

Модуль загружается автоматически через `ModuleServiceProvider`.

## Конфигурация

Настройки находятся в `app/Modules/Captcha/Config/captcha.php`:

```php
return [
    'driver' => 'yandex', // 'yandex', 'google', 'custom'
    
    'yandex' => [
        'site_key' => 'your-site-key',
        'secret_key' => 'your-secret-key',
    ],
    
    'google' => [
        'site_key' => 'your-site-key',
        'secret_key' => 'your-secret-key',
    ],
];