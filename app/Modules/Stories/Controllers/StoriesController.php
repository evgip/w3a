<?php

namespace App\Modules\Stories\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Validator;
use App\Core\Auth;
use App\Core\Session;
use App\Modules\Stories\Models\Story;

class StoriesController extends Controller
{
    /**
     * Отображение главной ленты историй (а также фильтрация по тегу)
     * 
     * @param string|null $tagname Параметр автоматически передается роутером из {tagname}
     */

	public function index(string $tagname = '', string $domain = ''): void
	{
		$request = new Request();
		$currentPage = (int)$request->getParams('page', 1);
		if ($currentPage < 1) $currentPage = 1;

		$perPage = config_int('constants.pagination.stories_per_page', 15);
		$offset = ($currentPage - 1) * $perPage;

		$storyModel = new Story();
		$showDeleted = \App\Core\Auth::isAdmin();
		
		// НОВОЕ: Получаем отфильтрованные теги пользователя
		$excludeTagIds = [];
		if (\App\Core\Auth::check()) {
			$filterModel = new \App\Modules\Tags\Models\TagFilter();
			$excludeTagIds = $filterModel->getFilteredTagIds(\App\Core\Auth::id());
		}
		
		// Передаем $excludeTagIds в модель для фильтрации
		$stories = $storyModel->getFeed($perPage, $offset, $tagname, $showDeleted, $domain, $excludeTagIds);
		
		// Получаем корректное количество записей с учетом фильтров
		$totalStories = $storyModel->getTotalCount($tagname, $domain, $excludeTagIds);
		$totalPages = (int)ceil($totalStories / $perPage);

		$title = 'Лента историй';
		if ($tagname) {
			$title = "Публикации с тегом # " . e($tagname);
		} elseif ($domain) {
			$title = "Публикации с домена " . e($domain);
		}

		$this->render('index', [
			'title' => $title,
			'stories' => $stories,
			'currentPage' => $currentPage,
			'totalPages' => $totalPages
		]);
	}
	

    /**
     * Display the submission form (GET /stories/create)
     */
    public function showCreateForm(): void
    {
        if (!\App\Core\Auth::check()) {
            \App\Core\Session::setFlash('error', 'Пожалуйста, войдите в систему, чтобы поделиться историей.');
            header('Location: /login');
            exit;
        }

        $request = new Request();
        
        // Pull master available checkboxes catalog from the cross-module Tag class model
        $tagModel = new \App\Modules\Tags\Models\Tag();
        $availableTags = $tagModel->getAllTags();

        $this->render('create', [
            'title' => 'Поделиться интересным',
            'availableTags'  => $availableTags,
            'request' => $request
        ]);
    }

    /**
     * Process story creation payloads (POST /stories/create)
     */
    public function create(): void
    {
        if (!\App\Core\Auth::check()) { header('Location: /login'); exit; }

        $request = new Request();
        $request->validateCsrf();

        $validator = new Validator();
		$minTitleLength = config_int('validation.title_min_length', 5);
		$isValid = $validator->validate($_POST, ['title' => "required|min:{$minTitleLength}"]);

        $title = $request->getParams('title');
        $url = $request->getParams('url') ?: null;
        $description = $request->getParams('description') ?: null;
        
        // Capture checked item integers list securely: $_POST['tags'] -> [1, 3]
        $selectedTags = $_POST['tags'] ?? [];

        if (!empty($url) && !isValidUrl($url)) {
            \App\Core\Session::setFlash('error', 'Пожалуйста, укажите корректный URL-адрес.');
            header('Location: /stories/create');
            exit;
        }

        if (!$isValid) {
            \App\Core\Session::setFlash('error', 'Заголовок должен содержать как минимум 5 символов.');
            header('Location: /stories/create');
            exit;
        }

        $storyModel = new Story();
        $newStoryId = $storyModel->create([
            'user_id' => (int)$_SESSION['user_id'],
            'title' => $title,
            'url' => $url,
            'description' => $description,
            'score' => 1,
            'comments_count' => 0,
			'user_is_following' => isset($_POST['user_is_following']) ? 1 : 0,
        ]);

        // Intercept and map chosen checkboxes into the pivot database partition
        if ($newStoryId > 0) {
            $storyModel->syncTags($newStoryId, $selectedTags);
        }

        \App\Core\Audit::log('story.created', 'Пользователь создал новую публикацию с тегами');
        \App\Core\Session::setFlash('success', 'Ваша история успешно опубликована!');
        header('Location: /');
        exit;
    }

    /**
     * Display the Story Editing Panel (GET /stories/{id}/edit)
     */
    public function showEditForm(string $id): void
    {
        if (!\App\Core\Auth::check()) { header('Location: /login'); exit; }

        $storyId = (int)$id;
        $storyModel = new Story();
        $story = $storyModel->find($storyId);

        if (!$story) { header('Location: /'); exit; }

        // PERMISSION ENFORCEMENT LAYER: Check if current account owns the target story row
        $isAuthor = ((int)$story['user_id'] === (int)$_SESSION['user_id']);
        if (!$isAuthor && !\App\Core\Auth::isAdmin()) {
            \App\Core\Session::setFlash('error', 'У вас нет прав для изменения этой публикации.');
            header('Location: /');
            exit;
        }

        $tagModel = new \App\Modules\Tags\Models\Tag();
        
        $this->render('edit', [
            'title' => 'Редактирование публикации',
            'story' => $story,
            'availableTags' => $tagModel->getAllTags(),
            'activeTagIds'  => $storyModel->getStoryTagIds($storyId),
            'request' => new Request()
        ]);
    }

    /**
     * Handle Update persistence transaction requests (POST /stories/{id}/edit)
     */
    public function update(string $id): void
    {
        if (!\App\Core\Auth::check()) { header('Location: /login'); exit; }

        $request = new Request();
        $request->validateCsrf();

        $storyId = (int)$id;
        $storyModel = new Story();
        $story = $storyModel->find($storyId);

        if (!$story) { header('Location: /'); exit; }

        if ((int)$story['user_id'] !== (int)$_SESSION['user_id'] && !\App\Core\Auth::isAdmin()) {
            header('Location: /');
            exit;
        }

        $title = $request->getParams('title');
        $url = $request->getParams('url') ?: null;
        $description = $request->getParams('description') ?: null;
        $selectedTags = $_POST['tags'] ?? [];

 

        $storyModel->update($storyId, [
            'title' => $title,
            'url' => $url,
            'description' => $description,
			'user_is_following' => isset($_POST['user_is_following']) ? 1 : 0,
        ]);

        // Sync the edited set of checkboxes into MySQL
        $storyModel->syncTags($storyId, $selectedTags);

        \App\Core\Session::setFlash('success', 'Публикация успешно отредактирована.');
        header('Location: /story/' . $storyId);
        exit;
    }

	
   /**
     * Просмотр одной истории и её дерева комментариев (GET /story/{id})
     */
   public function show(string $id): void
    {
        $storyId = (int)$id;
        $storyModel = new Story();
        
        $showDeleted = \App\Core\Auth::isAdmin();
        $story = $storyModel->getSingleWithAuthor($storyId, $showDeleted);
        
        if (!$story) {
            $errorController = "App\\Modules\\Errors\\Controllers\\ErrorsController";
            if (class_exists($errorController)) { (new $errorController())->notFound("История не найдена."); exit; }
            http_response_code(404); die("404 Not Found");
        }

        $flatComments = $storyModel->getCommentsForStory($storyId);
        $commentsTree = [];
        foreach ($flatComments as $comment) {
            $parentId = $comment['parent_id'] ?? 0;
            $commentsTree[$parentId][] = $comment;
        }

        $this->render('show', [
            'title' => $story['title'],
            'story' => $story,
            'commentsTree' => $commentsTree
        ]);
    }
	
	    /**
     * Administrative Moderation Override: Soft Delete Story (POST /admin/stories/{id}/delete)
     */
    public function adminDelete(string $id): void
    {
        \App\Core\Auth::middlewareAdmin(); // Block standard accounts
        
        $request = new Request();
        $request->validateCsrf();

        $storyId = (int)$id;
        $storyModel = new Story();
        
        // Execute core model inherited soft delete operation
        $storyModel->delete($storyId);

        \App\Core\Audit::log('admin.story_moderated', "Администратор принудительно скрыл публикацию ID: {$storyId}");
        \App\Core\Session::setFlash('success', 'Публикация успешно скрыта из общей ленты.');
        
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
        exit;
    }

    /**
     * Administrative Moderation Override: Restore Soft Deleted Story (POST /admin/stories/{id}/restore)
     */
    public function adminRestore(string $id): void
    {
        \App\Core\Auth::middlewareAdmin();
        
        $request = new Request();
        $request->validateCsrf();

        $storyId = (int)$id;
        $storyModel = new Story();
        
        // Execute core model inherited restore operation
        $storyModel->restore($storyId);

        \App\Core\Audit::log('admin.story_restored', "Администратор восстановил публикацию ID: {$storyId}");
        \App\Core\Session::setFlash('success', 'Публикация успешно восстановлена в общей ленте.');
        
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
        exit;
    }	
	
	/**
     * Process new comment submissions (POST /comments/create)
     */
	public function addComment(): void
	{
		// 1. Enforce active authentication
		if (!\App\Core\Auth::check()) {
			AppCoreSession::setFlash('error', 'Пожалуйста, войдите в систему, чтобы оставить комментарий.');
			header('Location: /login');
			exit;
		}
		
		$request = new Request();
		$request->validateCsrf();
		
		// 2. Clean incoming tracking parameters
		$storyId = (int)$request->getParams('story_id');
		$parentId = $request->getParams('parent_id') !== '' ? (int)$request->getParams('parent_id') : null;
		$commentText = $request->getParams('comment_text');
		
		// 3. Perform server-side field length validation checks
		$validator = new Validator();
		$minCommentLength = config_int('constants.validation.comment_min_length', 2);
		$isValid = $validator->validate(['comment_text' => $commentText], [
			'comment_text' => "required|min:{$minCommentLength}"
		]);
		
		if (!$isValid) {
			AppCoreSession::setFlash('error', "Текст комментария должен содержать не менее {$minCommentLength} символов.");
			header('Location: /story/' . $storyId);
			exit;
		}
		
  
		
		
		// 4. Trigger database storage transaction operations through the Comment model
		$commentModel = new \App\Modules\Stories\Models\Comment();
		$commentId = $commentModel->saveComment([
			'story_id' => $storyId,
			'user_id' => (int)$_SESSION['user_id'],
			'parent_id' => $parentId,
			'comment' => trim($commentText)
		]);
		
		if ($commentId > 0) {
			
			/*
			$notificationModel = new \App\Modules\Notifications\Models\Notification();
			$notificationModel->createForComment(
				$data['story_id'],
				$commentId,
				$data['user_id']
			); */
			
			// Отправляем уведомления
			$notificationService = new \App\Modules\Notifications\Services\NotificationService();
			$notificationService->notifyCommentCreated($commentId);
			
			\App\Core\Audit::log('comment.created', 'Пользователь оставил комментарий к истории', [
				'story_id' => $storyId,
				'parent_id' => $parentId
			]);
			\App\Core\Session::setFlash('success', 'Ваш комментарий успешно опубликован!');
			
			// Редирект к созданному комментарию с якорем
			header('Location: ' . comment_url($storyId, $commentId));
		} else {
			\App\Core\Session::setFlash('error', 'Произошла непредвиденная ошибка при публикации комментария.');
			header('Location: /story/' . $storyId);
		}
		
		exit;
	}
	
    /**
     * Редактирование текста комментария (POST /comments/{id}/edit)
     */
    public function editComment(string $id): void
    {
        if (!\App\Core\Auth::check()) { header('Location: /login'); exit; }

        $request = new Request();
        $request->validateCsrf();

        $commentId = (int)$id;
        $commentModel = new \App\Modules\Stories\Models\Comment();
        
        // Находим оригинальный комментарий, включая архивные (на случай скрытых проверок)
        $comment = $commentModel->withTrashed()->find($commentId);
        if (!$comment) { header('Location: /'); exit; }

        // ПРАВА: Изменять коммент может только автор или админ
        $isAuthor = (int)$comment['user_id'] === (int)$_SESSION['user_id'];
        $isAdmin = \App\Core\Auth::isAdmin();
        
        if (!$isAuthor && !$isAdmin) {
            \App\Core\Session::setFlash('error', 'У вас нет прав для изменения этого комментария.');
            header('Location: /story/' . $comment['story_id']);
            exit;
        }

        $newText = $request->getParams('comment_text');
        $validator = new Validator();
        $isValid = $validator->validate(['comment_text' => $newText], ['comment_text' => 'required|min:2']);

        if (!$isValid) {
            \App\Core\Session::setFlash('error', 'Текст комментария должен содержать не менее 2 символов.');
        } else {
            $commentModel->update($commentId, ['comment' => trim($newText)]);
            \App\Core\Audit::log('comment.updated', 'Пользователь отредактировал свой комментарий', ['comment_id' => $commentId]);
            \App\Core\Session::setFlash('success', 'Комментарий успешно обновлен.');
        }

        header('Location: /story/' . $comment['story_id']);
        exit;
    }

    /**
     * Мягкое удаление комментария (POST /comments/{id}/delete)
     */
    public function deleteComment(string $id): void
    {
        if (!\App\Core\Auth::check()) { header('Location: /login'); exit; }

        $request = new Request();
        $request->validateCsrf();

        $commentId = (int)$id;
        $commentModel = new \App\Modules\Stories\Models\Comment();
        $comment = $commentModel->find($commentId); // Ищем только среди активных

        if ($comment) {
            $isAuthor = (int)$comment['user_id'] === (int)$_SESSION['user_id'];
            if ($isAuthor || \App\Core\Auth::isAdmin()) {
                $commentModel->softDeleteComment($commentId, (int)$comment['story_id']);
                \App\Core\Session::setFlash('success', 'Комментарий успешно удален.');
            } else {
                \App\Core\Session::setFlash('error', 'Недостаточно прав для удаления.');
            }
            header('Location: /story/' . $comment['story_id']);
            exit;
        }
        header('Location: /');
    }

    /**
     * Восстановление мягко удаленного комментария (POST /comments/{id}/restore)
     */
    public function restoreComment(string $id): void
    {
        if (!\App\Core\Auth::check()) { header('Location: /login'); exit; }

        $request = new Request();
        $request->validateCsrf();

        $commentId = (int)$id;
        $commentModel = new \App\Modules\Stories\Models\Comment();
        $comment = $commentModel->withTrashed()->find($commentId);

        if ($comment && !empty($comment['deleted_at'])) {
            $isAuthor = (int)$comment['user_id'] === (int)$_SESSION['user_id'];
            if ($isAuthor || \App\Core\Auth::isAdmin()) {
                $commentModel->restoreComment($commentId, (int)$comment['story_id']);
                \App\Core\Session::setFlash('success', 'Комментарий успешно восстановлен из архива.');
            } else {
                \App\Core\Session::setFlash('error', 'Недостаточно прав для восстановления.');
            }
            header('Location: /story/' . $comment['story_id']);
            exit;
        }
        header('Location: /');
    }
	
	/**
	 * Переключить подписку на историю
	 */
	public function toggleFollow(string $id): void
	{
		if (!\App\Core\Auth::check()) { header('Location: /login'); exit; }
		
		$storyId = (int)$id;
		$userId = (int)$_SESSION['user_id'];
		
		$storyModel = new Story();
		$storyModel->toggleFollow($storyId, $userId);
		
		// Для AJAX-запросов
		if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
			$isFollowing = $storyModel->isFollowing($storyId, $userId);
			$this->json([
				'success' => true,
				'is_following' => $isFollowing,
			]);
			return;
		}
		
		// Для обычных форм — редирект обратно
		header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? "/story/{$storyId}"));
		exit;
	}
}

