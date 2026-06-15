<?php

/**
 * Глобальный хелпер для генерации URL по имени маршрута
 */
if (!function_exists('route')) {
    function route(string $name, array $params = []): string
    {
        global $router; // Используем глобальный объект роутера из index.php
        return $router->route($name, $params);
    }
}

/**
 * Глобальный хелпер для вывода локализованных строк перевода
 */
if (!function_exists('__')) {
    function __(string $key, array $replace = []): string
    {
        return \App\Core\Lang::get($key, $replace);
    }
}

if (!function_exists('declension')) {
    function declension(int $number, array $forms): string
    {
        $number = abs($number) % 100;
        $n1 = $number % 10;

        if ($number > 10 && $number < 20) {
            return $forms[2];
        }
        if ($n1 > 1 && $n1 < 5) {
            return $forms[1];
        }
        if ($n1 === 1) {
            return $forms[0];
        }
        return $forms[2];
    }
}


if (!function_exists('partial')) {
    /**
     * Подключение partial-шаблона из модуля
     * 
     * @param string $path   - путь вида 'Votes::_voters' или 'Users::_avatar'
     * @param array  $vars   - переменные для шаблона
     */
    function partial(string $path, array $vars = []): void
    {
        // Разбираем путь: "Votes::_voters" → модуль Votes, файл _voters.php
        [$module, $file] = explode('::', $path);
        $filePath = dirname(__DIR__) . "/app/Modules/{$module}/Views/{$file}.php";

        if (!file_exists($filePath)) {
            throw new \RuntimeException("Partial not found: {$filePath}");
        }

        // Извлекаем переменные в текущую область видимости
        extract($vars);
        include $filePath;
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('dt')) {
    /**
     * Форматирование даты
     */
    function dt(?string $datetime, string $format = 'd.m.Y H:i'): string
    {
        if (!$datetime) return '';
        return date($format, strtotime($datetime));
    }
}

if (!function_exists('plural')) {
    /**
     * Склонение числительных
     */
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

if (!function_exists('csrf_field')) {
    /**
     * Генерирует скрытое поле с CSRF-токеном
     */
    function csrf_field(): string
    {
        $request = new \App\Core\Request();
        return $request->csrfField();
    }
}

if (!function_exists('render_flashes')) {
    /**
     * Вывод flash-сообщений
     */
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

if (!function_exists('config')) {
    /**
     * Получить значение из конфигурации
     * 
     * @param string|null $key Ключ в формате 'file.group.key' или null для получения всего файла
     * @param mixed $default Значение по умолчанию
     * @return mixed
     * 
     * Примеры:
     *   config('config.app.name')                    // 'w3a'
     *   config('config.app.min_karma_for_downvote')  // 10
     *   config('constants.pagination.stories_per_page') // 15
     *   config('database.host', 'localhost')         // 'localhost'
     *   config('config')                             // весь файл config.php
     */
    function config(?string $key = null, mixed $default = null): mixed
    {
        // Если ключ не указан, возвращаем весь файл конфигурации
        if ($key === null) {
            return \App\Core\Config::getFile('config');
        }

        return \App\Core\Config::get($key, $default);
    }
}

if (!function_exists('config_int')) {
    /**
     * Получить целое число из конфигурации
     * 
     * @param string $key Ключ конфигурации
     * @param int $default Значение по умолчанию
     * @return int
     */
    function config_int(string $key, int $default = 0): int
    {
        return \App\Core\Config::getInt($key, $default);
    }
}

if (!function_exists('config_string')) {
    /**
     * Получить строку из конфигурации
     * 
     * @param string $key Ключ конфигурации
     * @param string $default Значение по умолчанию
     * @return string
     */
    function config_string(string $key, string $default = ''): string
    {
        return \App\Core\Config::getString($key, $default);
    }
}

if (!function_exists('config_bool')) {
    /**
     * Получить булево значение из конфигурации
     * 
     * @param string $key Ключ конфигурации
     * @param bool $default Значение по умолчанию
     * @return bool
     */
    function config_bool(string $key, bool $default = false): bool
    {
        return \App\Core\Config::getBool($key, $default);
    }
}

if (!function_exists('config_array')) {
    /**
     * Получить массив из конфигурации
     * 
     * @param string $key Ключ конфигурации
     * @param array $default Значение по умолчанию
     * @return array
     */
    function config_array(string $key, array $default = []): array
    {
        return \App\Core\Config::getArray($key, $default);
    }
}

if (!function_exists('config_has')) {
    /**
     * Проверить существование ключа в конфигурации
     * 
     * @param string $key Ключ конфигурации
     * @return bool
     */
    function config_has(string $key): bool
    {
        return \App\Core\Config::has($key);
    }
}

if (!function_exists('config_set')) {
    /**
     * Установить значение в конфигурации (только runtime)
     * 
     * @param string $key Ключ в формате 'file.group.key'
     * @param mixed $value Значение
     */
    function config_set(string $key, mixed $value): void
    {
        \App\Core\Config::set($key, $value);
    }
}

if (!function_exists('app_name')) {
    /**
     * Получить имя приложения
     */
    function app_name(): string
    {
        return config_string('config.app.name', 'w3a');
    }
}


if (!function_exists('comment_url')) {
    /**
     * Генерация URL для конкретного комментария
     * 
     * @param int $storyId ID истории
     * @param int $commentId ID комментария
     * @return string URL с якорем
     */
    function comment_url(int $storyId, int $commentId): string
    {
        $baseUrl = "/story/{$storyId}";
        $anchor = "comment-block-{$commentId}";  // ← Изменено здесь!

        return "{$baseUrl}#{$anchor}";
    }
}

if (!function_exists('isValidUrl')) {
    function isValidUrl(string $url): bool
    {
        // Базовая валидация формата
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parsed = parse_url($url);

        // Проверка схемы
        $allowedSchemes = ['http', 'https'];
        if (!in_array($parsed['scheme'] ?? '', $allowedSchemes, true)) {
            return false;
        }

        // Дополнительная защита: блокировка подозрительных символов
        if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return false;
        }

        // Проверка длины (защита от DoS)
        if (strlen($url) > 2048) {
            return false;
        }

        return true;
    }
}


/**
 * Для статей на главной
 */


 if (!function_exists('truncateDescription')) {
	/**
	 * Обрезает HTML текст
	 */
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
 
if (!function_exists('needsTruncation')) {
	/**
	 * Проверяет, нужно ли сворачивать текст
	 */
	function needsTruncation(string $html, int $length = 300): bool {
		$text = strip_tags($html);
		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		return mb_strlen($text) > $length;
	}
}


if (!function_exists('safeLink')) {
	/**
	 * Удаляем все атрибуты кроме href
	 */
	function safeLink(?string $text): string
	{
		if ($text === null || $text === '') return '—';
		
		// Разрешаем только <a href="...">
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