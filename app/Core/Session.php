<?php

namespace App\Core;

class Session
{
    private bool $started = false;

    /**
     * Конструктор — инициализирует сессию при создании
     */
    public function __construct()
    {
        $this->start();
    }

    /**
     * Запуск сессии
     */
    public function start(): void
    {
        if (!$this->started && session_status() === PHP_SESSION_NONE) {
            session_start();
            $this->started = true;
        }
    }

    /**
     * Получить значение из сессии
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Установить значение в сессии
     */
    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    /**
     * Проверить наличие ключа
     */
    public function has(string $key): bool
    {
        $this->start();
        return isset($_SESSION[$key]);
    }

    /**
     * Удалить значение из сессии
     */
    public function delete(string $key): void
    {
        $this->start();
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * Получить все данные сессии
     */
    public function all(): array
    {
        $this->start();
        return $_SESSION ?? [];
    }

    /**
     * Очистить всю сессию
     */
    public function clear(): void
    {
        $this->start();
        $_SESSION = [];
    }


	/**
	 * Уничтожить сессию
	 */
	public function destroy(): void
	{
		if (session_status() === PHP_SESSION_ACTIVE) {
			// Удаляем cookie сессии
			if (ini_get("session.use_cookies")) {
				$params = session_get_cookie_params();
				setcookie(
					session_name(),
					'',
					time() - 42000,
					$params["path"],
					$params["domain"],
					$params["secure"],
					$params["httponly"]
				);
			}
			
			session_destroy();
		}
		
		$_SESSION = [];
		$this->started = false;
	}

    /**
     * Установить flash-сообщение
     */
    public function flash(string $key, string $message): void
    {
        $this->start();
        $_SESSION['flash'][$key] = $message;
    }

    /**
     * Проверить наличие flash-сообщения
     */
    public function hasFlash(string $key): bool
    {
        $this->start();
        return isset($_SESSION['flash'][$key]);
    }

    /**
     * Получить и сразу удалить flash-сообщение
     */
    public function getFlash(string $key): ?string
    {
        $this->start();
        if (isset($_SESSION['flash'][$key])) {
            $message = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $message;
        }
        return null;
    }

    /**
     * Получить все flash-сообщения и очистить их
     */
    public function allFlashes(): array
    {
        $this->start();
        $flashes = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flashes;
    }

    /**
     * Получить ID текущей сессии
     */
    public function id(): string
    {
        $this->start();
        return session_id();
    }

    /**
     * Регенерировать ID сессии
     */
    public function regenerate(bool $deleteOldSession = false): bool
    {
        $this->start();
        return session_regenerate_id($deleteOldSession);
    }
}