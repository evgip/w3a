<?php

declare(strict_types=1);

namespace App\Core\Events;

use Throwable;

/**
 * Диспетчер событий.
 * 
 * Регистрирует слушателей для событий и рассылает им уведомления.
 * Если один слушатель упал с исключением — остальные продолжат работу.
 */
class EventDispatcher
{
    /** @var array<string, array<callable>> Слушатели событий, сгруппированные по классу */
    private array $listeners = [];

    /**
     * Зарегистрировать слушателя для события.
     *
     * @param string $eventClass Полное имя класса события (например, StoryCreated::class)
     * @param callable $listener Функция-слушатель, принимающая Event
     */
    public function listen(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * Отправить событие всем зарегистрированным слушателям.
     * 
     * Если один слушатель выбросит исключение — оно будет залогировано,
     * а остальные слушатели продолжат работу.
     */
    public function dispatch(Event $event): void
    {
        $eventClass = get_class($event);

        if (!isset($this->listeners[$eventClass])) {
            return;
        }

        foreach ($this->listeners[$eventClass] as $listener) {
            try {
                call_user_func($listener, $event);
            } catch (Throwable $e) {
                // Логируем ошибку, но не прерываем работу других слушателей
                error_log(sprintf(
                    '[EventDispatcher] Ошибка в слушателе события %s: %s в %s:%d',
                    $eventClass,
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                ));
            }
        }
    }

    /**
     * Проверить, есть ли зарегистрированные слушатели для события.
     * Полезно для отладки.
     */
    public function hasListeners(string $eventClass): bool
    {
        return !empty($this->listeners[$eventClass]);
    }

    /**
     * Получить список всех зарегистрированных событий.
     * Полезно для отладки.
     */
    public function getRegisteredEvents(): array
    {
        return array_keys($this->listeners);
    }
}