<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use App\Modules\Stories\Models\Comment;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Auth\Services\Auth;
use App\Core\Session;
use App\Core\Validator;
use App\Core\Events\EventDispatcher;
use App\Core\Events\CommentCreated;
use App\Core\Events\CommentDeleted;
use App\Core\Events\CommentRestored;
use App\Core\Events\CommentUpdated;

/**
 * Сервис для работы с комментариями.
 *
 * Отвечает за:
 *  - CRUD комментариев (создание, обновление, удаление, восстановление)
 *  - Валидацию данных
 *  - Проверку прав доступа
 *  - Диспатч событий (аудит, счётчики, уведомления обрабатываются слушателями)
 */
class CommentService
{
    private Comment $commentModel;
    private ?NotificationService $notificationService;
    private ?EventDispatcher $eventDispatcher;

    /**
     * Конструктор сервиса.
     *
     * @param Comment $commentModel Модель комментариев
     * @param NotificationService|null $notificationService Сервис уведомлений (опционально)
     * @param EventDispatcher|null $eventDispatcher Диспетчер событий (опционально)
     */
    public function __construct(
        Comment $commentModel,
        ?NotificationService $notificationService = null,
        ?EventDispatcher $eventDispatcher = null
    ) {
        $this->commentModel = $commentModel;
        $this->notificationService = $notificationService;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Создаёт новый комментарий.
     *
     * Выполняет:
     *  - Валидацию текста
     *  - Создание комментария в БД
     *  - Отправку уведомлений
     *  - Диспатч события CommentCreated
     *  - Установку flash-сообщения
     *
     * @param int $storyId ID истории
     * @param string $text Текст комментария
     * @param int|null $parentId ID родительского комментария
     * @param int $userId ID пользователя
     * @return array Данные для редиректа или пустой массив при ошибке
     */
    public function createComment(int $storyId, string $text, ?int $parentId, int $userId): array
    {
        // 1. Валидация текста
        if (!$this->validateCommentText($text)) {
            $minLength = config('constants.validation.comment_min_length', 2, 'int');
            Session::setFlash('error', "Текст комментария должен содержать не менее {$minLength} символов.");
            return [];
        }

        // 2. Создание комментария
        $commentData = [
            'story_id' => $storyId,
            'user_id' => $userId,
            'parent_id' => $parentId,
            'comment' => trim($text),
        ];

        $commentId = $this->commentModel->saveComment($commentData);

        if ($commentId > 0) {
            // 3. Уведомления
            if ($this->notificationService !== null && method_exists($this->notificationService, 'notifyCommentCreated')) {
                $this->notificationService->notifyCommentCreated($commentId);
            }

            // 4. Диспатч события
            $this->dispatchCommentEvent(new CommentCreated(
                $commentId,
                $storyId,
                $userId,
                $parentId
            ));

            // 5. Flash-сообщение
            Session::setFlash('success', 'Ваш комментарий успешно опубликован!');

            // 6. Возвращаем данные для редиректа
            return [
                'comment_id' => $commentId,
                'story_id' => $storyId,
            ];
        }

        return [];
    }

    /**
     * Обновляет текст комментария.
     *
     * Выполняет:
     *  - Проверку существования комментария
     *  - Проверку прав доступа
     *  - Валидацию нового текста
     *  - Обновление комментария в БД
     *  - Диспатч события CommentUpdated
     *
     * @param int $commentId ID комментария
     * @param string $newText Новый текст
     * @param int $userId ID текущего пользователя
     * @return array|null Данные комментария при успехе, null при ошибке
     */
    public function updateComment(int $commentId, string $newText, int $userId): ?array
    {
        $comment = $this->commentModel->withTrashed()->find($commentId);
        if (!$comment) {
            return null;
        }

        // Проверка прав
        if (!$this->canEditComment($comment, $userId)) {
            Session::setFlash('error', 'У вас нет прав для изменения этого комментария.');
            return null;
        }

        // Валидация
        if (!$this->validateCommentText($newText)) {
            Session::setFlash('error', 'Текст комментария должен содержать не менее 2 символов.');
            return null;
        }

        // Обновление
        $this->commentModel->update($commentId, ['comment' => trim($newText)]);

        // Диспатч события
        $this->dispatchCommentEvent(new CommentUpdated(
            $commentId,
            $userId,
            (bool) $this->checkIsAuthor($comment, $userId)
        ));

        // Возвращаем данные для контроллера
        return [
            'comment' => $comment,
            'is_author' => $this->checkIsAuthor($comment, $userId),
        ];
    }

    /**
     * Мягко удаляет комментарий.
     *
     * Выполняет:
     *  - Проверку существования комментария
     *  - Проверку прав доступа
     *  - Мягкое удаление (soft delete)
     *  - Установка flash-сообщения
     *  - Диспатч события CommentDeleted (аудит и счётчик обновляются через слушателей)
     *
     * @param int $commentId ID комментария
     * @param int $userId ID текущего пользователя
     * @return array|null Данные комментария при успехе, null при ошибке
     */
    public function deleteComment(int $commentId, int $userId): ?array
    {
        $comment = $this->commentModel->find($commentId);
        if (!$comment) {
            return null;
        }

        // Проверка прав
        if (!$this->canDeleteComment($comment, $userId)) {
            Session::setFlash('error', 'Недостаточно прав для удаления.');
            return null;
        }

        // Мягкое удаление
        $this->commentModel->softDeleteComment($commentId);
        Session::setFlash('success', 'Комментарий успешно удален.');

        // Диспатч события
        $this->dispatchCommentEvent(new CommentDeleted(
            $commentId,
            (int) $comment['story_id'],
            $userId,
            (bool) $this->checkIsAuthor($comment, $userId)
        ));

        // Возвращаем данные для контроллера
        return [
            'comment' => $comment,
            'story_id' => (int) $comment['story_id'],
            'is_author' => (bool) $this->checkIsAuthor($comment, $userId),
        ];
    }

    /**
     * Восстанавливает удалённый комментарий.
     *
     * Выполняет:
     *  - Проверку существования комментария (включая удалённые)
     *  - Проверку, что комментарий действительно удалён
     *  - Проверку прав доступа
     *  - Восстановление комментария
     *  - Установка flash-сообщения
     *  - Диспатч события CommentRestored
     *
     * @param int $commentId ID комментария
     * @param int $userId ID текущего пользователя
     * @return array|null Данные комментария при успехе, null при ошибке
     */
    public function restoreComment(int $commentId, int $userId): ?array
    {
        $comment = $this->commentModel->withTrashed()->find($commentId);

        if (!$comment) {
            return null;
        }

        if (empty($comment['deleted_at'])) {
            Session::setFlash('error', 'Комментарий не удалён.');
            return null;
        }

        if (!$this->canDeleteComment($comment, $userId)) {
            Session::setFlash('error', 'Недостаточно прав для восстановления.');
            return null;
        }

        // Восстановление
        $this->commentModel->restoreComment($commentId, (int) $comment['story_id']);
        Session::setFlash('success', 'Комментарий успешно восстановлен.');

        // Диспатч события
        $this->dispatchCommentEvent(new CommentRestored(
            $commentId,
            (int) $comment['story_id'],
            $userId,
            (bool) $this->checkIsAuthor($comment, $userId)
        ));

        return [
            'comment' => $comment,
            'story_id' => (int) $comment['story_id'],
            'is_author' => (bool) $this->checkIsAuthor($comment, $userId),
        ];
    }

    /**
     * Проверяет, может ли пользователь редактировать комментарий.
     *
     * Права есть у:
     *  - Автора комментария
     *  - Администратора
     *  - Модератора
     *
     * @param array $comment Данные комментария
     * @param int $userId ID текущего пользователя
     * @return bool Может ли пользователь редактировать
     */
    public function canEditComment(array $comment, int $userId): bool
    {
        $isAuthor = (int) $comment['user_id'] === $userId;
        $isAdmin = Auth::isAdmin();
        $isModerator = Auth::isModerator();

        return $isAuthor || $isAdmin || $isModerator;
    }

    /**
     * Проверяет, может ли пользователь удалять/восстанавливать комментарий.
     *
     * @param array $comment Данные комментария
     * @param int $userId ID текущего пользователя
     * @return bool Может ли пользователь удалять
     */
    public function canDeleteComment(array $comment, int $userId): bool
    {
        return $this->canEditComment($comment, $userId);
    }

    /**
     * Валидирует текст комментария.
     *
     * @param string $text Текст для валидации
     * @return bool Прошла ли валидация
     */
    private function validateCommentText(string $text): bool
    {
        $validator = new Validator();
        $minLength = config('constants.validation.comment_min_length', 2, 'int');
        return $validator->validate(['comment_text' => $text], ['comment_text' => "required|min:{$minLength}"]);
    }

    /**
     * Определяет, является ли пользователь автором комментария.
     *
     * @param array $comment Данные комментария
     * @param int $userId ID пользователя для проверки
     * @return int 1 если автор, 0 если нет
     */
    private function checkIsAuthor(array $comment, int $userId): int
    {
        return ((int) $comment['user_id'] === $userId) ? 1 : 0;
    }

    /**
     * Безопасно диспатчит событие через EventDispatcher.
     *
     * Если EventDispatcher не был передан в конструктор, событие не будет отправлено.
     *
     * @param \App\Core\Events\Event $event Событие для диспатча
     */
    private function dispatchCommentEvent(\App\Core\Events\Event $event): void
    {
        if ($this->eventDispatcher === null) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }
}
