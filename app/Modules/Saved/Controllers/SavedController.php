<?php

declare(strict_types=1);

namespace App\Modules\Saved\Controllers;

use App\Core\Controller;
use App\Modules\Saved\Models\SavedStory;
use App\Modules\Saved\Services\SavedService;
use App\Modules\Auth\Services\Auth;
use App\Modules\Stories\Services\StoryFilterService;
use App\Modules\Votes\Models\Vote;

class SavedController extends Controller
{
    /**
     * Список сохранённых историй
     */
    public function index(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login');
            return;
        }

        $userId = Auth::id();
        $currentPage = max(1, (int)$this->request->getParams('page', 1));
        $perPage = config('constants.pagination.stories_per_page', 15, 'int');
        $offset = ($currentPage - 1) * $perPage;

        $savedModel = $this->container->get(SavedStory::class);
        $stories = $savedModel->getUserSaved($userId, $perPage, $offset);
        $totalStories = $savedModel->getUserSavedCount($userId);
        $totalPages = (int)ceil($totalStories / $perPage);

        $storyIds = array_column($stories, 'id');
        $filterService = $this->service(StoryFilterService::class);
        $newCommentsMap = $filterService->getNewCommentsCounts($storyIds);

        // Голоса
        $currentVotes = [];
        if ($userId > 0) {
            $voteModel = $this->container->get(Vote::class);
            foreach ($storyIds as $storyId) {
                $currentVotes[$storyId] = $voteModel->getUserVote($userId, 'story', (int)$storyId);
            }
        }

        // Контекст голосования
        $canDownvote = false;
        if ($userId > 0) {
            $userModel = $this->container->get(\App\Modules\Users\Models\User::class);
            $karma = $userModel->getUserKarma($userId);
            $minKarma = (int)config('config.app.min_karma_for_downvote', 10);
            $canDownvote = $karma >= $minKarma;
        }

        $this->render('index', [
            'stories' => $stories,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'newCommentsMap' => $newCommentsMap,
            'bannedDomainsCache' => $filterService->getBannedDomains(),
            'sort' => 'saved',
            'currentUserId' => $userId,
            'isAdmin' => Auth::isAdmin(),
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
        if (!Auth::check()) {
            $this->json(['error' => 'Необходима авторизация'], 401);
            return;
        }

        $storyId = (int)$id;
        $userId = Auth::id();

        $savedService = $this->service(SavedService::class);
        $isSaved = $savedService->toggle($userId, $storyId);

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