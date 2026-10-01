<?php

declare(strict_types=1);

namespace App\Modules\Users\Controllers;

use App\BaseController;
use W3a\Core\Http\Session;
use W3a\Core\Http\Response;
use W3a\Core\Http\ViewResponse;
use W3a\Core\Http\RedirectResponse;
use W3a\Core\Exceptions\NotFoundException;
use W3a\Core\Support\MessageBag;

use App\Modules\Users\Services\UserService;
use App\Modules\Users\Services\AvatarService;
use App\Modules\Users\Models\User;
use App\Modules\Users\Exceptions\UserValidationException;
use App\Modules\Users\Exceptions\UserNotFoundException;
use App\Modules\Users\Exceptions\AvatarUploadException;

use App\Modules\Users\Requests\UpdateSettingsRequest;
use App\Modules\Users\Requests\ChangePasswordRequest;


use App\Modules\Common\Support\Layout; 

class UsersController extends BaseController
{
    private function getUserService(): UserService
    {
        return $this->service(UserService::class);
    }

    private function getAvatarService(): AvatarService
    {
        return $this->service(AvatarService::class);
    }

    public function index(): ViewResponse
    {
        return $this->render('index', [
            'title' => 'Участники',
        ]);
    }

	public function profile(string $username): ViewResponse
	{
		return $this->profileView($username, 'stories');
	}

	/**
	 * Вкладка «Коллекции» профиля пользователя.
	 * URL: /@{username}/collections
	 */
	public function profileCollections(string $username): ViewResponse
	{
		return $this->profileView($username, 'collections');
	}

	private function profileView(string $username, string $activeTab): ViewResponse
	{
		$user = $this->getUserByUsername(trim($username));

		$profile = $user['profile'] ?? [];
		$user['bio'] = $profile['bio'] ?? null;
		$user['avatar'] = $profile['avatar'] ?? null;

		$userModel = $this->container->get(User::class);
		$banInfo = $userModel->getBanInfo((int)$user['id']);
		$user['is_banned'] = $banInfo !== null;
		
		$stats = $userModel->getProfileStats((int)$user['id']);
		$userKarma = $userModel->getUserKarma((int)$user['id']);
		$followersCount = $this->container->get(\App\Modules\Subscriptions\Models\FollowedUser::class)->getFollowersCount((int)$user['id']);

		$userContext = $this->getUserContext();

		$isMuted = false;
		$isFollowing = false;
		if ($userContext['isLoggedIn'] && (int)$user['id'] !== $userContext['id']) {
			$muteService = $this->service(\App\Modules\Muted\Services\MuteService::class);
			$isMuted = $muteService->isMuted($userContext['id'], (int)$user['id']);

			$subscriptionService = $this->service(\App\Modules\Subscriptions\Services\SubscriptionService::class);
			$isFollowing = $subscriptionService->isFollowingUser($userContext['id'], (int)$user['id']);
		}

		$feed = null;
		if ($activeTab === 'stories') {
			$feed = $this->service(\App\Modules\Stories\Services\StoryFeedBuilder::class)->build(
				tagslug: '',
				author: $username,
				userContext: $userContext,
				pageData: ['title' => 'Публикации ' . e($username)]
			);

			// Пометка «новое»: только для чужих статей, которые текущий пользователь ещё не открывал.
			// Свои статьи не считаем непрочитанными (автор их «знает»).
			$isOwnProfile = $userContext['isLoggedIn'] && $userContext['id'] === (int)$user['id'];
			if (!empty($userContext['isLoggedIn']) && !$isOwnProfile && !empty($feed->stories)) {
				$storyView = $this->container->get(\App\Modules\Stories\Models\StoryView::class);
				$viewedIds = $storyView->getViewedStoryIds((int)$userContext['id'], 200);
				$viewedMap = array_fill_keys(array_map('intval', $viewedIds), true);
				foreach ($feed->stories as &$s) {
					$s['is_unread'] = empty($viewedMap[(int)$s['id']]);
				}
				unset($s);
			} elseif (!empty($feed->stories)) {
				// На своём профиле (или для юзера) не подсвечиваем
				foreach ($feed->stories as &$s) {
					$s['is_unread'] = false;
				}
				unset($s);
			}
		}

		$collectionModel = $this->container->get(\App\Modules\Collections\Models\Collection::class);
		$allCollections = $collectionModel->getByAuthor((int)$user['id']);
		
		$isOwner = $userContext['isLoggedIn'] && $userContext['id'] === (int)$user['id'];
		if (!$isOwner) {
			$allCollections = array_filter($allCollections, fn($c) => !empty($c['is_public']));
			$allCollections = array_values($allCollections);
		}
		
		$collectionsCount = count($allCollections);
		
		$coverService = $this->service(\App\Modules\Collections\Services\CollectionCoverService::class);
		foreach ($allCollections as &$collection) {
			$collection['cover_url'] = !empty($collection['cover_image'])
				? $coverService->getCoverUrl($collection['cover_image'])
				: null;
		}
		unset($collection);

// 🔑 Устанавливаем широкий макет для профиля
		Layout::set(Layout::WIDE);

return $this->render('profile', [
			'title' => 'Профиль пользователя ' . e($user['username']),
			'profileUser' => $user,
			'userKarma' => $userKarma ?? 0,
			'isMuted' => $isMuted,
			'isFollowing' => $isFollowing,
			'activeTab' => $activeTab,
			'storiesCount' => $stats['stories_count'] ?? ($feed ? count($feed->stories) : 0),
			'commentsCount' => $stats['comments_count'] ?? 0,
			'collectionsCount' => $collectionsCount,
			'collectionsAll' => $allCollections,
			'isOwner' => $isOwner,
			'followersCount' => $followersCount,
			'stories' => $feed ? $feed->stories : [],
			'currentPage' => $feed ? $feed->currentPage : 1,
			'totalPages' => $feed ? $feed->totalPages : 0,
			'hasUnreadStories' => $activeTab === 'stories' && !$isOwner
				&& !empty($feed) && collect($feed->stories)->contains(fn($s) => !empty($s['is_unread'])),
		]);
	}

    public function settings(): Response
    {
        $userContext = $this->getUserContext();

        $userOrRedirect = $this->getUserWithProfileOrRedirect($userContext['id']);

        if ($userOrRedirect instanceof RedirectResponse) {
            return $userOrRedirect;
        }
        
        $user = $userOrRedirect;
        $settings = $this->getUserService()->getUserSettings($userContext['id']);

        // Настройки — форма: узкий центрированный макет (как вход/регистрация)
        Layout::set(Layout::NARROW);

        return $this->render('settings', [
            'title' => 'Настройки профиля',
            'user' => $user,
            'settings' => $settings,
            'request' => $this->request
        ]);
    }

	public function updateSettings(): RedirectResponse
	{
		$userContext = $this->getUserContext();
		$userOrRedirect = $this->getUserWithProfileOrRedirect($userContext['id']);

		if ($userOrRedirect instanceof RedirectResponse) {
			return $userOrRedirect;
		}
		
		$user = $userOrRedirect;
		$targetUrl = route('account.settings');

		$validated = $this->validateForm(
			new UpdateSettingsRequest($this->request, $this->container)
		);
		
		if ($validated instanceof Response) {
			return $this->redirect($targetUrl);
		}

		try {
			$email = trim($validated['email'] ?? '');
			if ($email !== $user['email']) {
				$this->getUserService()->updateEmail($userContext['id'], $email);
			}

			$oldAvatarFilename = $user['avatar'] ?? '';
			$newAvatarFilename = $oldAvatarFilename;

			$avatarFile = $this->request->file('avatar_file');
			if ($avatarFile && $avatarFile['error'] === UPLOAD_ERR_OK) {
				$newAvatarFilename = $this->getAvatarService()->handleUpload($avatarFile, $oldAvatarFilename);
			}

			$bio = trim($validated['bio'] ?? '');
			$this->getUserService()->updateProfile($userContext['id'], [
				'bio'    => $bio,
				'avatar' => $newAvatarFilename
			]);

			$this->container->get(Session::class)->set('user_avatar', $newAvatarFilename);

			// Обновляем настройки уведомлений только если в форме ЕСТЬ соответствующие поля.
			// Если форма отправлена без галочек (например, форма аватара) — пропускаем,
			// чтобы не сбрасывать все уведомления в ноль.
			if ($this->hasNotificationFields()) {
				$this->getUserService()->updateSettings($userContext['id'], [
					'notify_on_reply'               => (int)($validated['notify_on_reply'] ?? 0),
					'notify_on_story_comment'       => (int)($validated['notify_on_story_comment'] ?? 0),
					'notify_on_mention'             => (int)($validated['notify_on_mention'] ?? 0),
					'notify_on_message'             => (int)($validated['notify_on_message'] ?? 0),
					'notify_on_collection_update'   => (int)($validated['notify_on_collection_update'] ?? 0),
					'email_notifications'           => (int)($validated['email_notifications'] ?? 0),
				]);
			}

			MessageBag::flashMessage('success', 'Настройки успешно сохранены.');
			return $this->redirect($targetUrl);

		} catch (UserValidationException | AvatarUploadException $e) {
			MessageBag::flashMessage('error', $e->getMessage());
			return $this->redirect($targetUrl);

		} catch (\Throwable $e) {
			$this->logError($e, 'Users.updateSettings');
			MessageBag::flashMessage('error', 'Произошла непредвиденная ошибка при сохранении.');
			return $this->redirect($targetUrl);
		}
	}

	/**
	 * Проверяет, есть ли в текущем POST-запросе поля настроек уведомлений.
	 * Нужно чтобы отличать сабмит формы профиля (аватар/email/bio) от формы уведомлений.
	 */
	private function hasNotificationFields(): bool
	{
		$keys = [
			'notify_on_reply',
			'notify_on_story_comment',
			'notify_on_mention',
			'notify_on_message',
			'notify_on_collection_update',
			'email_notifications',
		];

		foreach ($keys as $key) {
			if ($this->request->has([$key])) {
				return true;
			}
		}

		return false;
	}

	public function updatePassword(): RedirectResponse
	{
		$userContext = $this->getUserContext();
		$targetUrl = route('account.settings');

		$validated = $this->validateForm(
			new ChangePasswordRequest($this->request, $this->container)
		);
		
		if ($validated instanceof Response) {
			return $this->redirect($targetUrl);
		}

		try {
			$this->getUserService()->changePassword(
				$userContext['id'],
				$validated['current_password'],
				$validated['new_password']
			);
			
			MessageBag::flashMessage('success', 'Пароль успешно изменён.');
			return $this->redirect($targetUrl);
			
		} catch (UserValidationException | UserNotFoundException $e) {
			MessageBag::flashMessage('error', $e->getMessage());
			return $this->redirect($targetUrl);

		} catch (\Throwable $e) {
			$this->logError($e, 'Users.updatePassword');
			MessageBag::flashMessage('error', 'Произошла непредвиденная ошибка.');
			return $this->redirect($targetUrl);
		}
	}

	/**
	 * Пометить все публикации автора как прочитанные (Medium-style «Mark all as read»).
	 */
	public function markAllRead(string $username): \W3a\Core\Http\RedirectResponse
	{
		$userContext = $this->getUserContext();
		if (!$userContext['isLoggedIn']) {
			return $this->redirect('/login');
		}

		try {
			$author = $this->getUserByUsername(trim($username));
			$authorId = (int)$author['id'];

			// Опубликованные статьи автора
			$storyModel = $this->container->get(\App\Modules\Stories\Models\Story::class);
			$stories = $storyModel->getPublishedByAuthor($authorId);
			$storyIds = array_column($stories, 'id');

			$storyView = $this->container->get(\App\Modules\Stories\Models\StoryView::class);
			$storyView->markStoriesAsRead((int)$userContext['id'], $storyIds);

			MessageBag::flashMessage('success', 'Все публикации автора отмечены как прочитанные.');
		} catch (\Throwable $e) {
			$this->logError($e, 'Users.markAllRead');
			MessageBag::flashMessage('error', 'Не удалось отметить публикации как прочитанные.');
		}

		return $this->redirectBack();
	}

	private function getUserByUsername(string $username): array
    {
        $userModel = $this->container->get(User::class);
        $user = $userModel->findBy('username', $username);

        if (!$user) {
            throw new NotFoundException("Пользователь <i>{$username}</i> не найден.");
        }

        $user['profile'] = $userModel->getProfile((int)$user['id']);
        return $user;
    }

    private function getUserWithProfileOrRedirect(int $userId): array|RedirectResponse
    {
        $user = $this->getUserService()->getUserWithProfile($userId);

        if (!$user) {
            return $this->redirect('/');
        }

        return $user;
    }
}
