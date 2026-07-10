<?php

declare(strict_types=1);

namespace App\Modules\Comments\Controllers;

use App\Core\Controller;
use App\Modules\Comments\Services\CommentService;
use App\Modules\Auth\Services\Auth;

class CommentsController extends Controller
{
    /**
     * Глобальная лента всех комментариев (как в Lobsters /comments)
     */
	public function index(): void
	{
		$currentUserId = Auth::check() ? Auth::id() : 0;
		
		// Получаем last_read_comments_at
		$lastReadAt = null;
		if ($currentUserId > 0) {
			$userModel = $this->container->get(\App\Modules\Users\Models\User::class);
			$user = $userModel->find($currentUserId);
			if ($user) {
				$lastReadAt = $user['last_read_comments_at'] ?? null;
			}
			
			// Автоматическая отметка прочтения при просмотре (логика Lobsters)
			$userModel->updateLastReadComments($currentUserId);
		}

		// Получаем последние комментарии
		$commentService = $this->service(CommentService::class);
		$comments = $commentService->getLatestComments(50);

		// Обновляем read_ribbons для всех историй, комментарии которых показаны
		if ($currentUserId > 0 && !empty($comments)) {
			$readRibbonService = $this->service(\App\Modules\Stories\Services\ReadRibbonService::class);
			
			// Получаем уникальные story_id из комментариев
			$storyIds = array_unique(array_column($comments, 'story_id'));

			$readRibbonService->markStoriesAsRead($storyIds);
		}

		// Получаем голоса для комментариев
		$currentCommentVotes = [];
		if ($currentUserId > 0 && !empty($comments)) {
			$voteModel = $this->container->get(\App\Modules\Votes\Models\Vote::class);
			$commentIds = array_map('intval', array_column($comments, 'id'));
			
			$currentCommentVotes = $voteModel->getUserVotesForComments(
				$currentUserId, $commentIds
			);
		}
		

		// Контекст голосования
		$canDownvote = false;
		if ($currentUserId > 0) {
			$userModel = $this->container->get(\App\Modules\Users\Models\User::class);
			$karma = $userModel->getUserKarma($currentUserId);
			$minKarma = (int)config('config.app.min_karma_for_downvote', 10);
			$canDownvote = $karma >= $minKarma;
		}

		$this->render('index', [
			'comments' => $comments,
			'lastReadAt' => $lastReadAt,
			'currentUserId' => $currentUserId,
			'isAdmin' => Auth::isAdmin(),
			'isModerator' => Auth::isModerator(),
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
		$userId = Auth::id();

		$result = $this->service(CommentService::class)->createComment($storyId, $commentText, $parentId, $userId);

		if (!empty($result)) {
			$this->redirect(comment_url($result['story_id'], $result['comment_id']));
		} else {
			$this->redirect('/story/' . $storyId);
		}
	}

	/**
	 * Редактирование комментария
	 */
	public function edit(string $id): void
	{
		$commentId = (int)$id;
		$newText = $this->request->getParams('comment_text');
		$userId = Auth::id();

		$session = $this->container->get(\App\Core\Session::class);
		$result = $this->service(CommentService::class)->updateComment($commentId, $newText, $userId);

		// AJAX-ответ
		if ($this->request->isAjaxRequest()) {
			header('Content-Type: application/json; charset=utf-8');
			
			if ($result === null) {
				// Ошибка — читаем flash-сообщение из сессии
				$error = $session->getFlash('error') ?? 'Не удалось обновить комментарий';
				echo json_encode([
					'success' => false,
					'error' => $error
				], JSON_UNESCAPED_UNICODE);
				exit;
			}

			// Успех — рендерим Markdown в HTML
			$html = markdown_comment($result['comment']['comment']);
			echo json_encode([
				'success' => true,
				'html' => $html,
				'raw' => $result['comment']['comment']
			], JSON_UNESCAPED_UNICODE);
			exit;
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
        $commentId = (int) $id;
        $userId = Auth::id();

        $result = $this->service(CommentService::class)->deleteComment($commentId, $userId);

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
        $commentId = (int) $id;
        $userId = Auth::id();

        $result = $this->service(CommentService::class)->restoreComment($commentId, $userId);

        if ($result === null) {
            $this->redirectBack();
            return;
        }

        $this->redirect(comment_url($result['story_id'], $commentId));
    }
	
	public function userComments(string $username): void
	{
		$userModel = $this->container->get(\App\Modules\Users\Models\User::class);
		$user = $userModel->findByName($username);
		
		if (!$user) {
			throw new \App\Core\Exceptions\NotFoundException("Пользователь не найден");
		}
		
		$comments = $this->service(CommentService::class)->getUserComments((int)$user['id'], 50);
		
		$this->render('user_comments', [
			'profileUser' => $user,
			'comments' => $comments,
			'currentUserId' => Auth::check() ? Auth::id() : 0,
			'isAdmin' => Auth::isAdmin(),
			'isModerator' => Auth::isModerator(),
			'title' => 'Комментарии пользователя ' . e($username),
		]);
	}
}