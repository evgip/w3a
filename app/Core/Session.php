<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Сервис для работы с сессиями
 */
class Session
{
    private bool $started = false;

    public function __construct()
    {
        $this->start();
    }

    /**
     * Запуск сессии с защитой от повторного запуска
     */
    public function start(): void
    {
        if ($this->started) {
            return;
        }

        // Если сессия уже активна (запущена где-то ещё)
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            if (!session_start()) {
                throw new \RuntimeException('Failed to start session');
            }
            $this->started = true;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function delete(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Алиас для delete() - более привычное имя
     */
    public function remove(string $key): void
    {
        $this->delete($key);
    }

    public function all(): array
    {
        return $_SESSION ?? [];
    }

    public function clear(): void
    {
        $_SESSION = [];
    }

    /**
     * Уничтожить сессию полностью
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
     * ✅ Теперь принимает mixed вместо string
     */
    public function flash(string $key, mixed $message): void
    {
        $_SESSION['flash'][$key] = $message;
    }

    public function hasFlash(string $key): bool
    {
        return isset($_SESSION['flash'][$key]);
    }

    /**
     * ✅ Теперь возвращает mixed
     */
    public function getFlash(string $key): mixed
    {
        if (isset($_SESSION['flash'][$key])) {
            $message = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $message;
        }
        return null;
    }

    public function allFlashes(): array
    {
        $flashes = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flashes;
    }

    public function id(): string
    {
        return session_id();
    }

    /**
     * ✅ По умолчанию удаляем старую сессию для безопасности
     */
    public function regenerate(bool $deleteOldSession = true): bool
    {
        return session_regenerate_id($deleteOldSession);
    }

    /**
     * Получить имя сессии
     */
    public function name(): string
    {
        return session_name();
    }
}
