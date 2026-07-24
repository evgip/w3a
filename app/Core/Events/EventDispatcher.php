<?php

declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Logger;
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
    
    private Logger $logger;

    /**
     * Внедряем ваш собственный логгер
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Зарегистрировать слушателя для события.
     *
     * @param string $eventClass Полное имя класса события (например, StoryCreated::class)
     * @param callable $listener Функция-слушатель, принимающая Event
     */
    public function listen(string $eventClass, callable $listener): void
    {
        if (!isset($this->listeners[$eventClass])) {
            $this->listeners[$eventClass] = [];
        }

        // Получаем идентификатор слушателя для проверки на дубликаты
        $listenerId = $this->getListenerId($listener);

        // Защита от дублирования: если такой слушатель уже есть, не добавляем его повторно.
        // Это предотвращает баги вроде удвоения comments_count на уровне ядра.
        foreach ($this->listeners[$eventClass] as $existingListener) {
            if ($this->getListenerId($existingListener) === $listenerId) {
                return; 
            }
        }

        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * Отправить событие всем зарегистрированным слушателям.
     * 
     * Если один слушатель выбросит исключение — оно будет залогировано
     * через App\Core\Logger, а остальные слушатели продолжат работу.
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
                // ЗАМЕНЕНО: вместо error_log используем ваш Logger
                $this->logger->error("Ошибка в слушателе события {$eventClass}", [
                    'listener' => $this->getListenerId($listener),
                    'message'  => $e->getMessage(),
                    'file'     => $e->getFile(),
                    'line'     => $e->getLine()
                ]);
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

    /**
     * Внутренний метод для получения строкового идентификатора слушателя.
     * Нужен для защиты от дубликатов и для красивого вывода в лог.
     */
    private function getListenerId(callable $listener): string
    {
        if (is_array($listener)) {
            $class = is_object($listener[0]) ? get_class($listener[0]) : $listener[0];
            return $class . '::' . $listener[1];
        }
        
        if ($listener instanceof \Closure) {
            return 'Closure';
        }
        
        if (is_string($listener)) {
            return $listener;
        }
        
        if (is_object($listener)) {
            return get_class($listener) . '::__invoke';
        }

        return 'Unknown';
    }
}
