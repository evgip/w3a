<?php

declare(strict_types=1);

namespace App\Modules\Saved\Controllers;

use App\Core\Controller;
use App\Modules\Saved\Models\SavedStory;
use App\Modules\Saved\Services\SavedService;
use App\Modules\Stories\Services\StoryFilterService;
use App\Modules\Votes\Models\Vote;
use App\Modules\Users\Models\User;

/**
 * Контроллер сохранённых историй (закладок).
 * 
 * Обрабатывает:
 * - Список сохранённых историй с пагинацией
 * - AJAX toggle закладок
 */
class SavedController extends Controller
{
    /**
     * Список сохранённых историй
     */
    public function index(): void
    {
        $userContext = $this->getUserContext();

        if (!$userContext['isLoggedIn']) {
            $this->redirect('/login');
            return;
        }

        $currentPage = max(1, (int)$this->request->getParams('page', 1));
        $perPage = config('constants.pagination.stories_per_page', 15, 'int');
        $offset = ($currentPage - 1) * $perPage;

        $savedModel = $this->container->get(SavedStory::class);
        $stories = $savedModel->getUserSaved($userContext['id'], $perPage, $offset);
        $totalStories = $savedModel->getUserSavedCount($userContext['id']);
        $totalPages = (int)ceil($totalStories / $perPage);

        $storyIds = array_column($stories, 'id');
        $filterService = $this->service(StoryFilterService::class);
        $newCommentsMap = $filterService->getNewCommentsCounts($storyIds);

        $currentVotes = [];
        if (!empty($storyIds)) {
            $voteModel = $this->container->get(Vote::class);
            $currentVotes = $voteModel->getUserVotesForStories($userContext['id'], $storyIds);
        }

        $canDownvote = false;
        $userModel = $this->container->get(User::class);
        $karma = $userModel->getUserKarma($userContext['id']);
        $minKarma = (int)config('config.app.min_karma_for_downvote', 10);
        $canDownvote = $karma >= $minKarma;

        $this->render('index', [
            'stories' => $stories,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'newCommentsMap' => $newCommentsMap,
            'bannedDomainsCache' => $filterService->getBannedDomains(),
            'sort' => 'saved',
            'currentUserId' => $userContext['id'],
            'isAdmin' => $userContext['isAdmin'],
            'canUserDownvote' => $canDownvote,
            'currentVotes' => $currentVotes,
            'title' => 'Мои закладки',
        ]);
    }

    /**
     * AJAX toggle закладки
     */
    public function toggle(string $id): void
    {
        $userContext = $this->getUserContext();

        if (!$userContext['isLoggedIn']) {
            $this->json(['error' => 'Необходима авторизация'], 401);
            return;
        }

        $storyId = (int)$id;

        $savedService = $this->service(SavedService::class);
        $isSaved = $savedService->toggle($userContext['id'], $storyId);

        // Поддержка AJAX и обычных запросов
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $this->json([
                'success' => true,
                'is_saved' => $isSaved,
            ]);
            return;
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? '/saved';
        $this->redirectBack($referer);
    }
}
