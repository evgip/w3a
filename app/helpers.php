<?php


use App\Core\Router;

/**
 * Генерация URL по имени маршрута
 * 
 * @example route('home') → '/'
 * @example route('story.show', ['id' => 123]) → '/story/123'
 */
function route(string $name, array $params = []): string
{
    $router = Router::getInstance();
    
    if ($router === null) {
        // Fallback: Router ещё не инициализирован (например, в CLI)
        $router = new Router(new \App\Core\Request());
    }
    
    return $router->route($name, $params);
}

/**
 * Проверка, является ли текущий маршрут указанным
 * 
 * @example if (is_route('home')) { echo 'active'; }
 */
function is_route(string $name): bool
{
    $router = Router::getInstance();
    return $router !== null && $router->getCurrentRouteName() === $name;
}

/**
 * Безопасный редирект "назад"
 */
function back_url(string $fallback = '/'): string
{
    $referer = $_SERVER['HTTP_REFERER'] ?? $fallback;
    
    // Разрешаем только относительные URL или свой домен
    if (str_starts_with($referer, '/') && !str_starts_with($referer, '//')) {
        return $referer;
    }
    
    $refererHost = parse_url($referer, PHP_URL_HOST);
    $appHost = $_SERVER['HTTP_HOST'] ?? '';
    
    return ($refererHost === $appHost) ? $referer : $fallback;
}

/**
 * Global helper to output localized translation strings
 * Глобальный хелпер для вывода локализованных строк перевода
 */
if (!function_exists('__')) {
    function __(string $key, array $replace = []): string
    {
        return \App\Core\Lang::get($key, $replace);
    }
}

/**
 * Include a partial template from a module
 * Подключение partial-шаблона из модуля
 *
 * @param string $path   - Path in format 'Votes::_voters' or 'Users::_avatar'
 *                       - Путь в формате 'Votes::_voters' или 'Users::_avatar'
 * @param array  $vars   - Variables to pass to the template
 *                       - Переменные для передачи в шаблон
 */
if (!function_exists('partial')) {
    function partial(string $path, array $vars = []): void
    {
        // Parse path: "Votes::_voters" → module Votes, file _voters.php
        // Разбор пути: "Votes::_voters" → модуль Votes, файл _voters.php
        [$module, $file] = explode('::', $path);
        $filePath = dirname(__DIR__) . "/app/Modules/{$module}/Views/{$file}.php";

        if (!file_exists($filePath)) {
            throw new \RuntimeException("Partial not found: {$filePath}");
        }

        // Extract variables into local scope
        // Извлечение переменных в локальную область видимости
        extract($vars);
        include $filePath;
    }
}

/**
 * HTML-escape a string (null-safe)
 * HTML-экранирование строки (безопасно при null)
 */
if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Format a datetime string (nullable input)
 * Форматирование даты и времени (допускается null)
 */
if (!function_exists('dt')) {
    function dt(?string $datetime, string $format = 'd.m.Y H:i'): string
    {
        if (!$datetime) return '';
        return date($format, strtotime($datetime));
    }
}

/**
 * Decline numerals in Russian (0, 1, 2-4, 5-20+ forms)
 * Склонение числительных на русском (формы: 1, 2-4, 5-20+)
 */
if (!function_exists('plural')) {
    function plural(int $n, array $forms): string
    {
        $n = abs($n) % 100;
        $n1 = $n % 10;
        if ($n > 10 && $n < 20) return $forms[2];
        if ($n1 > 1 && $n1 < 5) return $forms[1];
        if ($n1 === 1) return $forms[0];
        return $forms[2];
    }
}

/**
 * Generate a hidden CSRF token field
 * Генерация скрытого поля с CSRF-токеном
 */
if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        $request = new \App\Core\Request();
        return $request->csrfField();
    }
}

/**
 * Render flash messages by type (success, error, notice)
 * Вывод flash-сообщений по типу (успех, ошибка, информационное)
 */
if (!function_exists('render_flashes')) {
    function render_flashes(): void
    {
        $types = [
            'success' => 'alert-success',
            'error'   => 'alert-danger',
            'notice'  => 'alert-notice'
        ];

        foreach ($types as $key => $class) {
            if (\App\Core\Session::hasFlash($key)) {
                $message = htmlspecialchars(\App\Core\Session::getFlash($key));
                $title = $key === 'success' ? 'Успех' : ($key === 'error' ? 'Ошибка' : 'Информация');

                echo '<div class="alert ' . $class . '">';
                echo '<strong>' . $title . '!</strong> ' . $message;
                echo '</div>';
            }
        }
    }
}

/**
 * Retrieve configuration value(s)
 * Получение значений из конфигурации
 *
 * @param string|null $key    - Key in format 'file.group.key' or null for full file
 *                            - Ключ в формате 'файл.группа.ключ' или null для всего файла
 * @param mixed $default      - Default value if key not found
 *                            - Значение по умолчанию при отсутствии ключа
 * @return mixed
 *
 * Examples:
 *   config('config.app.name')                    // 'w3a'
 *   config('config.app.min_karma_for_downvote')  // 10
 *   config('constants.pagination.stories_per_page') // 15
 *   config('database.host', 'localhost')         // 'localhost'
 *   config('config')                             // entire config.php file
 *
 *   Примеры:
 *     config('config.app.name')                    // 'w3a'
 *     config('config.app.min_karma_for_downvote')  // 10
 *     config('constants.pagination.stories_per_page') // 15
 *     config('database.host', 'localhost')         // 'localhost'
 *     config('config')                             // весь файл config.php
 */
if (!function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return \App\Core\Config::getFile('config');
        }

        return \App\Core\Config::get($key, $default);
    }
}

/**
 * Retrieve integer configuration value
 * Получение целочисленного значения из конфигурации
 *
 * @param string $key     - Configuration key
 *                        - Ключ конфигурации
 * @param int    $default - Default value
 *                        - Значение по умолчанию
 * @return int
 */
if (!function_exists('config_int')) {
    function config_int(string $key, int $default = 0): int
    {
        return \App\Core\Config::getInt($key, $default);
    }
}

/**
 * Retrieve string configuration value
 * Получение строкового значения из конфигурации
 *
 * @param string $key     - Configuration key
 *                        - Ключ конфигурации
 * @param string $default - Default value
 *                        - Значение по умолчанию
 * @return string
 */
if (!function_exists('config_string')) {
    function config_string(string $key, string $default = ''): string
    {
        return \App\Core\Config::getString($key, $default);
    }
}

/**
 * Retrieve boolean configuration value
 * Получение булевого значения из конфигурации
 *
 * @param string $key     - Configuration key
 *                        - Ключ конфигурации
 * @param bool   $default - Default value
 *                        - Значение по умолчанию
 * @return bool
 */
if (!function_exists('config_bool')) {
    function config_bool(string $key, bool $default = false): bool
    {
        return \App\Core\Config::getBool($key, $default);
    }
}

/**
 * Retrieve array configuration value
 * Получение массива из конфигурации
 *
 * @param string $key     - Configuration key
 *                        - Ключ конфигурации
 * @param array  $default - Default value
 *                        - Значение по умолчанию
 * @return array
 */
if (!function_exists('config_array')) {
    function config_array(string $key, array $default = []): array
    {
        return \App\Core\Config::getArray($key, $default);
    }
}

/**
 * Check if configuration key exists
 * Проверка существования ключа в конфигурации
 *
 * @param string $key - Configuration key
 *                    - Ключ конфигурации
 * @return bool
 */
if (!function_exists('config_has')) {
    function config_has(string $key): bool
    {
        return \App\Core\Config::has($key);
    }
}

/**
 * Set configuration value at runtime (not persisted)
 * Установка значения конфигурации в runtime (не сохраняется между запросами)
 *
 * @param string $key   - Key in format 'file.group.key'
 *                      - Ключ в формате 'файл.группа.ключ'
 * @param mixed  $value - Value to set
 *                      - Значение для установки
 */
if (!function_exists('config_set')) {
    function config_set(string $key, mixed $value): void
    {
        \App\Core\Config::set($key, $value);
    }
}

/**
 * Retrieve application name
 * Получение названия приложения
 */
if (!function_exists('app_name')) {
    function app_name(): string
    {
        return config_string('config.app.name', 'w3a');
    }
}

/**
 * Generate URL for a specific comment with anchor
 * Генерация URL для конкретного комментария с якорем
 *
 * @param int $storyId    - Story ID
 *                        - ID истории
 * @param int $commentId  - Comment ID
 *                        - ID комментария
 * @return string URL with fragment identifier (e.g., `/story/123#comment-block-456`)
 *                URL с якорем (например, `/story/123#comment-block-456`)
 */
if (!function_exists('comment_url')) {
    function comment_url(int $storyId, int $commentId): string
    {
        $baseUrl = "/story/{$storyId}";
        $anchor = "comment-block-{$commentId}";  // ← Updated here

        return "{$baseUrl}#{$anchor}";
    }
}

/**
 * Validate URL (scheme, format, length, safe characters)
 * Валидация URL (схема, формат, длина, безопасные символы)
 */
if (!function_exists('isValidUrl')) {
    function isValidUrl(string $url): bool
    {
        // Basic format validation
        // Базовая валидация формата
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parsed = parse_url($url);

        // Scheme check
        // Проверка схемы
        $allowedSchemes = ['http', 'https'];
        if (!in_array($parsed['scheme'] ?? '', $allowedSchemes, true)) {
            return false;
        }

        // Block control characters
        // Блокировка управляющих символов
        if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return false;
        }

        // Length check (DoS protection)
        // Проверка длины (защита от DoS)
        if (strlen($url) > 2048) {
            return false;
        }

        return true;
    }
}

/**
 * Truncate HTML to plain text with ellipsis (length ~300 chars)
 * Обрезка HTML до обычного текста с многоточием (~300 символов)
 */
if (!function_exists('truncateDescription')) {
    function truncateDescription(string $html, int $length = 300): string {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        if (mb_strlen($text) > $length) {
            $text = mb_substr($text, 0, $length);
            $text = preg_replace('/ [^ ]*$/u', '', $text);
            $text .= '…';
        }
        
        return $text;
    }
}

/**
 * Check if HTML description needs truncation (length > 300 chars)
 * Проверка, нужно ли обрезать описание (длина > 300 символов)
 */
if (!function_exists('needsTruncation')) {
    function needsTruncation(string $html, int $length = 300): bool {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return mb_strlen($text) > $length;
    }
}

/**
 * Sanitize HTML links: keep only <a> with clean href attribute
 * Очистка HTML-ссылок: оставить только <a> с безопасным href
 */
if (!function_exists('safeLink')) {
    function safeLink(?string $text): string
    {
        if ($text === null || $text === '') return '—';
        
        // Allow only <a> tags
        // Разрешить только теги <a>
        $clean = strip_tags($text, '<a>');

        $clean = preg_replace_callback('/<a\s+([^>]*)>/i', function($m) {
            if (preg_match('/href\s*=\s*["\']([^"\']+)["\']/i', $m[1], $href)) {
                return '<a href="' . htmlspecialchars($href[1], ENT_QUOTES) . '">';
            }
            return '<a>';
        }, $clean);
        
        return $clean;
    }
}

/**
 * Распарсить объединённые теги в структурированный массив
 */
if (!function_exists('rparseTagsCombined')) {
    function parseTagsCombined(array &$story): void
    {
        $story['tags'] = !empty($story['tag_list']) ? explode(',', $story['tag_list']) : [];

        $tagsWithNames = [];
        if (!empty($story['tags_combined'])) {
            foreach (explode(',', $story['tags_combined']) as $pair) {
                list($tag, $name) = explode('||', $pair);
                $tagsWithNames[] = [
                    'tag' => $tag,
                    'name' => $name
                ];
            }
        }
        $story['tags_with_names'] = $tagsWithNames;
        unset($story['tags_combined']);
    }
}


/**
 * Вычисляет confidence score по формуле Вильсона (Wilson score interval)
 * Calculate confidence score using Wilson score interval
 * 
 * @param int $score Рейтинг комментария (апвоты минус флаги) / Comment rating (upvotes minus flags)
 * @param int $flags Количество флагов / Number of flags
 * @return float Значение от 0 до 1 / Value from 0 to 1
 * 
 * @see http://evanmiller.org/how-not-to-sort-by-average-rating.html
 * @see https://github.com/reddit/reddit/blob/master/r2/r2/lib/db/_sorts.pyx
 */
if (!function_exists('wilson_score')) {
    function wilson_score(int $score, int $flags): float 
    {
        $ups = $score + $flags;
        $downs = $flags;
        $n = $ups + $downs;
        
        if ($n === 0) {
            return 0.0;
        }
        
        if ($n < 0) {
            throw new \InvalidArgumentException(
                "n should count number of upvotes + flags; that can't be a negative number"
            );
        }
        
        $z = 1.281551565545;
        $p = $ups / $n;
        $zSquared = $z * $z;
        
        $left = $p + (1 / (2 * $n) * $zSquared);
        $right = $z * sqrt(($p * ((1 - $p) / $n)) + ($zSquared / (4 * $n * $n)));
        $under = 1.0 + ((1.0 / $n) * $zSquared);
        
        $confidence = ($left - $right) / $under;
        
        return max(0.0, min(1.0, $confidence));
    }
}

/**
 * @param int    $score          Суммарный рейтинг (upvotes - downvotes)
 * @param string $createdAt      Дата публикации (MySQL datetime)
 * @param array  $tagHotnessMods Массив модификаторов тегов (float)
 * @return float
 */
if (!function_exists('calculate_hotness')) {
    function calculate_hotness(int $score, string $createdAt, array $tagHotnessMods = []): float
    {
        // 1. Сумма модификаторов тегов (base)
        $base = array_sum($tagHotnessMods);
        
        // 2. Логарифмический рейтинг
        $order = log10(max(abs($score), 1));
        $sign  = $score > 0 ? 1 : ($score < 0 ? -1 : 0);
        
        // 3. Секунды с эпохи Reddit/Lobsters (11 декабря 2005, 00:00:00 UTC)
        $epoch   = 1134316800; 
        $seconds = strtotime($createdAt) - $epoch;
        
        // 4. Финальная формула с инверсией (отрицание)
        $hotness = -(($sign * $order + $seconds / 45000) + $base);
        
        return round($hotness, 7);
    }
}

/**
 * Получить значение из переменных окружения (.env)
 *
 * @param string $key Ключ
 * @param mixed $default Значение по умолчанию
 * @return mixed
 */
if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return \App\Core\Env::get($key, $default);
    }
}


// ═══════════════════════════════════════════
// 🔤 ХЕЛПЕРЫ ДЛЯ РАБОТЫ С КОНТЕНТОМ (MARKDOWN)
// ═══════════════════════════════════════════

if (!function_exists('markdown')) {
    /**
     * Парсинг Markdown в HTML (полный режим - для постов/историй)
     * 
     * @param string|null $text Markdown текст
     * @param bool $allowImages Разрешить изображения (по умолчанию true)
     * @return string HTML
     * 
     * Пример:
     *   echo markdown('# Привет **мир**');
     *   // <h1>Привет <strong>мир</strong></h1>
     */
    function markdown(?string $text, bool $allowImages = true): string
    {
        return \App\Modules\Content\Core\Markdown::parse($text, $allowImages);
    }
}

if (!function_exists('markdown_comment')) {
    /**
     * Парсинг Markdown для комментариев (ограниченный режим - без картинок)
     * 
     * @param string|null $text Markdown текст
     * @return string HTML
     * 
     * Пример:
     *   echo markdown_comment('Отличный пост! ![img](http://...)');
     *   // <p>Отличный пост! ![img](http://...)</p>  ← картинка НЕ отобразится
     */
    function markdown_comment(?string $text): string
    {
        return \App\Modules\Content\Core\Markdown::parseComment($text);
    }
}

if (!function_exists('markdown_plain')) {
    /**
     * Парсинг простого текста (без Markdown, только экранирование и переносы строк)
     * 
     * @param string|null $text Обычный текст
     * @return string HTML
     * 
     * Пример:
     *   echo markdown_plain("Привет\nмир!");
     *   // <p>Привет<br />мир!</p>
     */
    function markdown_plain(?string $text): string
    {
        return \App\Modules\Content\Core\Markdown::parsePlain($text);
    }
}

if (!function_exists('markdown_clear_cache')) {
    /**
     * Очистить кэш Markdown
     * 
     * Полезно при изменении настроек парсера или после массового обновления контента
     * 
     * @return void
     */
    function markdown_clear_cache(): void
    {
        \App\Modules\Content\Core\Markdown::clearCache();
    }
}
