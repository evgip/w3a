<?php

declare(strict_types=1);

namespace App\Modules\Comments\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\Exceptions\NotFoundException;
use App\Modules\Comments\Services\CommentService;
use App\Modules\Votes\Models\Vote;
use App\Modules\Users\Models\User;
use App\Modules\Stories\Services\ReadRibbonService;

/**
 * Контроллер комментариев.
 * 
 * Обрабатывает:
 * - Глобальную ленту комментариев
 * - Создание/редактирование/удаление комментариев
 * - AJAX-обновления комментариев
 * - Просмотр комментариев пользователя
 */
class CommentsController extends Controller
{
    /**
     * Глобальная лента всех комментариев (как в Lobsters /comments)
     */
    public function index(): void
    {
        $userContext = $this->getUserContext();

        // Получаем last_read_comments_at
        $lastReadAt = null;
        if ($userContext['isLoggedIn']) {
            $userModel = $this->container->get(User::class);
            $user = $userModel->find($userContext['id']);
            if ($user) {
                $lastReadAt = $user['last_read_comments_at'] ?? null;
            }

            // Автоматическая отметка прочтения при просмотре (логика Lobsters)
            $userModel->updateLastReadComments($userContext['id']);
        }

        // Получаем последние комментарии
        $commentService = $this->service(CommentService::class);
        $comments = $commentService->getLatestComments(50);

        // Обновляем read_ribbons для всех историй, комментарии которых показаны
        if ($userContext['isLoggedIn'] && !empty($comments)) {
            $readRibbonService = $this->service(ReadRibbonService::class);

            // Получаем уникальные story_id из комментариев
            $storyIds = array_unique(array_column($comments, 'story_id'));

            $readRibbonService->markStoriesAsRead($storyIds);
        }

        // Получаем голоса для комментариев
        $currentCommentVotes = [];
        if ($userContext['isLoggedIn'] && !empty($comments)) {
            $voteModel = $this->container->get(Vote::class);
            $commentIds = array_map('intval', array_column($comments, 'id'));

            $currentCommentVotes = $voteModel->getUserVotesForComments(
                $userContext['id'],
                $commentIds
            );
        }

        $canDownvote = false;
        if ($userContext['isLoggedIn']) {
            $userModel = $this->container->get(User::class);
            $karma = $userModel->getUserKarma($userContext['id']);
            $minKarma = (int)config('config.app.min_karma_for_downvote', 10);
            $canDownvote = $karma >= $minKarma;
        }

        $this->render('index', [
            'comments' => $comments,
            'lastReadAt' => $lastReadAt,
            'currentUserId' => $userContext['id'],
            'isAdmin' => $userContext['isAdmin'],
            'isModerator' => $userContext['isModerator'],
            'canDownvote' => $canDownvote,
            'currentCommentVotes' => $currentCommentVotes,
            'title' => 'Последние комментарии',
            'rssFeed' => [
                'title' => 'Новые комментарии',
                'url' => '/comments/rss',
            ],
        ]);
    }

    /**
     * Создание комментария
     */
    public function create(): void
    {
        $storyId = (int)$this->request->getParams('story_id');

        // Корректная обработка null, пустой строки и "0"
        $parentIdRaw = $this->request->getParams('parent_id');

        if ($parentIdRaw === null || $parentIdRaw === '' || $parentIdRaw === '0' || $parentIdRaw === 0) {
            $parentId = null;
        } else {
            $parentId = (int)$parentIdRaw;
            // Дополнительная защита: ID должен быть положительным
            if ($parentId <= 0) {
                $parentId = null;
            }
        }

        $commentText = $this->request->getParams('comment_text');

        $userContext = $this->getUserContext();

        $result = $this->service(CommentService::class)->createComment($storyId, $commentText, $parentId, $userContext['id']);

        if (!empty($result)) {
            $this->redirect(comment_url($result['story_id'], $result['comment_id']));
        } else {
            $this->redirect('/story/' . $storyId);
        }
    }

    /**
     * Редактирование комментария
     * 
     * Поддерживает два режима:
     * - AJAX: возвращает JSON с обновлённым HTML
     * - Обычный POST: redirect на страницу комментария
     */
    public function edit(string $id): void
    {
        $commentId = (int)$id;
        $newText = $this->request->getParams('comment_text');

        $userContext = $this->getUserContext();

        $session = $this->container->get(Session::class);
        $result = $this->service(CommentService::class)->updateComment($commentId, $newText, $userContext['id']);

        // AJAX-ответ
        if ($this->request->isAjaxRequest()) {
            if ($result === null) {
                // Ошибка — читаем flash-сообщение из сессии
                $error = $session->getFlash('error') ?? 'Не удалось обновить комментарий';

                $this->json([
                    'success' => false,
                    'error' => $error
                ], 400);
            }

            // Успех — рендерим Markdown в HTML
            $html = markdown_comment($result['comment']['comment']);

            $this->json([
                'success' => true,
                'html' => $html,
                'raw' => $result['comment']['comment']
            ]);
        }

        // Обычный POST — redirect (fallback) если JS отключён
        if ($result === null) {
            $this->redirectBack();
            return;
        }

        $this->redirect(comment_url((int)$result['comment']['story_id'], $commentId));
    }

    /**
     * Удаление комментария
     */
    public function delete(string $id): void
    {
        $commentId = (int)$id;

        $userContext = $this->getUserContext();

        $result = $this->service(CommentService::class)->deleteComment($commentId, $userContext['id']);

        if ($result === null) {
            $this->redirectBack();
            return;
        }

        $this->redirect(comment_url($result['story_id'], $commentId));
    }

    /**
     * Восстановление комментария
     */
    public function restore(string $id): void
    {
        $commentId = (int)$id;

        $userContext = $this->getUserContext();

        $result = $this->service(CommentService::class)->restoreComment($commentId, $userContext['id']);

        if ($result === null) {
            $this->redirectBack();
            return;
        }

        $this->redirect(comment_url($result['story_id'], $commentId));
    }

    /**
     * Комментарии конкретного пользователя
     */
    public function userComments(string $username): void
    {
        $userModel = $this->container->get(User::class);
        $user = $userModel->findByName($username);

        if (!$user) {
            throw new NotFoundException("Пользователь не найден");
        }

        $comments = $this->service(CommentService::class)->getUserComments((int)$user['id'], 50);

        $userContext = $this->getUserContext();

        $this->render('user_comments', [
            'profileUser' => $user,
            'comments' => $comments,
            'currentUserId' => $userContext['id'],
            'isAdmin' => $userContext['isAdmin'],
            'isModerator' => $userContext['isModerator'],
            'title' => 'Комментарии пользователя ' . e($username),
        ]);
    }
}
