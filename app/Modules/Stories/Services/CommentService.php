<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use App\Modules\Stories\Models\Comment;
use App\Core\Auth;
use App\Core\Session;
use App\Core\Validator;
use App\Core\Audit;

/**
 * Сервис для работы с комментариями.
 * Отвечает только за CRUD комментариев, валидацию и проверку прав.
 * Уведомления обрабатываются отдельно в контроллере.
 */
class CommentService
{
    private Comment $commentModel;
    private ?object $notificationService;

    public function __construct(Comment $commentModel, ?object $notificationService = null)
    {
        $this->commentModel = $commentModel;
        $this->notificationService = $notificationService;
    }

    /**
     * Создаёт новый комментарий.
     *
     * @param int $storyId ID истории
     * @param string $text Текст комментария
     * @param int|null $parentId ID родительского комментария
     * @param int $userId ID пользователя
     * @return int ID созданного комментария или 0 при ошибке
     */
    public function createComment(int $storyId, string $text, ?int $parentId, int $userId): int
    {
        // 1. Валидация текста
        if (!$this->validateCommentText($text)) {
            $minLength = config_int('constants.validation.comment_min_length', 2);
            Session::setFlash('error', "Текст комментария должен содержать не менее {$minLength} символов.");
            return 0;
        }

        // 2. Создание комментария
        $commentData = [
            'story_id' => $storyId,
            'user_id' => $userId,
            'parent_id' => $parentId,
            'comment' => trim($text),
        ];

        $commentId = $this->commentModel->saveComment($commentData);

        // 3. Логирование
        if ($commentId > 0) {
            if ($this->notificationService !== null && method_exists($this->notificationService, 'notifyCommentCreated')) {
                $this->notificationService->notifyCommentCreated($commentId);
            }
        }

        return $commentId;
    }

    /**
     * Обновляет текст комментария.
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

        // Возвращаем данные для контроллера
        return [
            'comment' => $comment,
            'is_author' => $this->checkIsAuthor($comment, $userId),
        ];
    }

    /**
     * Мягко удаляет комментарий.
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

        // Удаление
		$this->commentModel->softDeleteComment($commentId);
		Session::setFlash('success', 'Комментарий успешно удален.');

        // Возвращаем данные для контроллера
        return [
            'comment' => $comment,
            'story_id' => (int)$comment['story_id'],
            'is_author' => (bool)$this->checkIsAuthor($comment, $userId),
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
            Session::setFlash('error', 'Комментарий не удалён.');
            return null;
        }

        if (!$this->canDeleteComment($comment, $userId)) {
            Session::setFlash('error', 'Недостаточно прав для восстановления.');
            return null;
        }

        // Передаём оба параметра: ID комментария и ID истории
        $this->commentModel->restoreComment($commentId, (int)$comment['story_id']);
        Session::setFlash('success', 'Комментарий успешно восстановлен.');

        return [
            'comment' => $comment,
            'story_id' => (int)$comment['story_id'],
            'is_author' => (bool)$this->checkIsAuthor($comment, $userId),
        ];
    }

    /**
     * Проверяет, может ли пользователь редактировать комментарий.
     */
    public function canEditComment(array $comment, int $userId): bool
    {
        $isAuthor = (int)$comment['user_id'] === $userId;
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
     */
    private function validateCommentText(string $text): bool
    {
        $validator = new Validator();
        $minLength = config_int('constants.validation.comment_min_length', 2);
        return $validator->validate(['comment_text' => $text], ['comment_text' => "required|min:{$minLength}"]);
    }

    /**
     * Определяет, является ли пользователь автором комментария.
     * Возвращает 1 (автор) или 0 (не автор)
     */
    private function checkIsAuthor(array $comment, int $userId): int
    {
        return ((int)$comment['user_id'] === $userId) ? 1 : 0;
    }
}