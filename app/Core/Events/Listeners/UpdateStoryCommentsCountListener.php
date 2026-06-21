<?php

declare(strict_types=1);

namespace App\Core\Events\Listeners;

use App\Core\Events\CommentCreated;
use App\Core\Events\CommentDeleted;
use App\Core\Events\CommentRestored;
use App\Modules\Stories\Models\Story;

/**
 * Слушатель событий комментариев для синхронизации счётчика комментариев в историях.
 *
 * Отвечает за поддержание актуального значения поля `comments_count` в таблице `stories`.
 * Регистрируется в `EventServiceProvider` на три события:
 *  - CommentCreated   → увеличивает счётчик на +1
 *  - CommentDeleted   → уменьшает счётчик на -1
 *  - CommentRestored  → увеличивает счётчик на +1
 *
 * Использует атомарный SQL-запрос с GREATEST(0, ...) для защиты от отрицательных значений
 * в случае рассинхронизации данных.
 */
class UpdateStoryCommentsCountListener
{
    /**
     * @var Story Модель историй для обновления счётчика
     */
    private Story $storyModel;

    /**
     * Конструктор слушателя.
     *
     * Инициализирует модель Story для выполнения операций обновления счётчика.
     */
    public function __construct()
    {
        $this->storyModel = new Story();
    }

    /**
     * Обработчик события создания комментария.
     *
     * Увеличивает счётчик комментариев у связанной истории на единицу.
     *
     * @param CommentCreated $event Событие создания комментария
     *
     * @return void
     */
    public function handleCreated(CommentCreated $event): void
    {
        $this->storyModel->incrementCommentsCount($event->getStoryId(), 1);
    }

    /**
     * Обработчик события удаления комментария.
     *
     * Уменьшает счётчик комментариев у связанной истории на единицу.
     * Атомарный SQL-запрос гарантирует, что счётчик не станет отрицательным.
     *
     * @param CommentDeleted $event Событие удаления комментария
     *
     * @return void
     */
    public function handleDeleted(CommentDeleted $event): void
    {
        $this->storyModel->incrementCommentsCount($event->getStoryId(), -1);
    }

    /**
     * Обработчик события восстановления комментария.
     *
     * Увеличивает счётчик комментариев у связанной истории на единицу.
     *
     * @param CommentRestored $event Событие восстановления комментария
     *
     * @return void
     */
    public function handleRestored(CommentRestored $event): void
    {
        $this->storyModel->incrementCommentsCount($event->getStoryId(), 1);
    }
}