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
 * ✅ ИЗМЕНЕНО: Session и Validator внедряются через конструктор.
 */
class CommentService
{
    private Comment $commentModel;
    private ?NotificationService $notificationService;
    private ?EventDispatcher $eventDispatcher;
    private Session $session;
    private Validator $validator;

    /**
     * ✅ ИЗМЕНЕНО: Добавлены Session и Validator в конструктор
     */
    public function __construct(
        Comment $commentModel,
        Session $session,
        Validator $validator,
        ?NotificationService $notificationService = null,
        ?EventDispatcher $eventDispatcher = null
    ) {
        $this->commentModel = $commentModel;
        $this->session = $session;
        $this->validator = $validator;
        $this->notificationService = $notificationService;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Создаёт новый комментарий.
     */
    public function createComment(int $storyId, string $text, ?int $parentId, int $userId): array
    {
        // 1. Валидация текста
        if (!$this->validateCommentText($text)) {
            $minLength = config('constants.validation.comment_min_length', 2, 'int');
            // ✅ Используем внедрённый Session
            $this->session->flash('error', "Текст комментария должен содержать не менее {$minLength} символов.");
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
            // ✅ Используем внедрённый Session
            $this->session->flash('success', 'Ваш комментарий успешно опубликован!');

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
     */
    public function updateComment(int $commentId, string $newText, int $userId): ?array
    {
        $comment = $this->commentModel->withTrashed()->find($commentId);
        if (!$comment) {
            return null;
        }

        // Проверка прав
        if (!$this->canEditComment($comment, $userId)) {
            $this->session->flash('error', 'У вас нет прав для изменения этого комментария.');
            return null;
        }

        // Валидация
        if (!$this->validateCommentText($newText)) {
            $this->session->flash('error', 'Текст комментария должен содержать не менее 2 символов.');
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

        return [
            'comment' => $comment,
            'is_author' => $this->checkIsAuthor($comment, $userId),
        ];
    }

    /**
     * Мягко удаляет комментарий.
     */
    public function deleteComment(int $commentId, int $userId): ?array
    {
        $comment = $this->commentModel->find($commentId);
        if (!$comment) {
            return null;
        }

        // Проверка прав
        if (!$this->canDeleteComment($comment, $userId)) {
            $this->session->flash('error', 'Недостаточно прав для удаления.');
            return null;
        }

        // Мягкое удаление
        $this->commentModel->softDeleteComment($commentId);
        $this->session->flash('success', 'Комментарий успешно удален.');

        // Диспатч события
        $this->dispatchCommentEvent(new CommentDeleted(
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
     * Восстанавливает удалённый комментарий.
     */
    public function restoreComment(int $commentId, int $userId): ?array
    {
        $comment = $this->commentModel->withTrashed()->find($commentId);

        if (!$comment) {
            return null;
        }

        if (empty($comment['deleted_at'])) {
            $this->session->flash('error', 'Комментарий не удалён.');
            return null;
        }

        if (!$this->canDeleteComment($comment, $userId)) {
            $this->session->flash('error', 'Недостаточно прав для восстановления.');
            return null;
        }

        // Восстановление
        $this->commentModel->restoreComment($commentId, (int) $comment['story_id']);
        $this->session->flash('success', 'Комментарий успешно восстановлен.');

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
     */
    public function canDeleteComment(array $comment, int $userId): bool
    {
        return $this->canEditComment($comment, $userId);
    }

    /**
     * Валидирует текст комментария.
     * 
     * ✅ ИЗМЕНЕНО: Используем внедрённый Validator
     */
    private function validateCommentText(string $text): bool
    {
        $minLength = config('constants.validation.comment_min_length', 2, 'int');
        return $this->validator->validate(
            ['comment_text' => $text], 
            ['comment_text' => "required|min:{$minLength}"]
        );
    }

    /**
     * Определяет, является ли пользователь автором комментария.
     */
    private function checkIsAuthor(array $comment, int $userId): int
    {
        return ((int) $comment['user_id'] === $userId) ? 1 : 0;
    }

    /**
     * Безопасно диспатчит событие через EventDispatcher.
     */
    private function dispatchCommentEvent(\App\Core\Events\Event $event): void
    {
        if ($this->eventDispatcher === null) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }
}