<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Сервис для управления Open Graph мета-тегами.
 * 
 * Данные устанавливаются в контроллерах и рендерятся в layout.
 */
class OpenGraph
{
    private static array $data = [
        'type' => 'website',
        'site_name' => null,
        'title' => null,
        'description' => null,
        'image' => null,
        'url' => null,
        'locale' => 'ru_RU',
    ];
    
    private static array $extra = []; // Для article:author, og:video и т.д.
    
    /**
     * Установить основные OG-данные
     */
    public static function set(array $data): void
    {
        foreach ($data as $key => $value) {
            if (array_key_exists($key, self::$data)) {
                self::$data[$key] = $value;
            } else {
                self::$extra[$key] = $value;
            }
        }
    }
    
    /**
     * Установить отдельное свойство
     */
    public static function setProperty(string $key, string $value): void
    {
        if (array_key_exists($key, self::$data)) {
            self::$data[$key] = $value;
        } else {
            self::$extra[$key] = $value;
        }
    }
    
    /**
     * Получить все данные
     */
    public static function getData(): array
    {
        return array_merge(self::$data, self::$extra);
    }
    
    /**
     * Сбросить данные (для тестов)
     */
    public static function reset(): void
    {
        self::$data = [
            'type' => 'website',
            'site_name' => null,
            'title' => null,
            'description' => null,
            'image' => null,
            'url' => null,
            'locale' => 'ru_RU',
        ];
        self::$extra = [];
    }
    
    /**
     * Сгенерировать HTML мета-тегов
     */
    public static function render(): string
    {
        $tags = [];
        
        // Заполняем site_name по умолчанию из конфига
        if (empty(self::$data['site_name'])) {
            self::$data['site_name'] = config('app.name', 'W3a', 'string');
        }
        
        foreach (self::$data as $key => $value) {
            if ($value !== null && $value !== '') {
                $tags[] = sprintf(
                    '<meta property="og:%s" content="%s">',
                    e($key),
                    e((string) $value)
                );
            }
        }
        
        foreach (self::$extra as $key => $value) {
            if ($value !== null && $value !== '') {
                $tags[] = sprintf(
                    '<meta property="%s" content="%s">',
                    e($key),
                    e((string) $value)
                );
            }
        }
        
        return implode("\n    ", $tags);
    }
}