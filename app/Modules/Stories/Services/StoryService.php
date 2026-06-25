<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use App\Modules\Stories\Models\Story;
use App\Modules\Origins\Models\Domain;
use App\Core\Session;
use App\Core\Validator;
use App\Core\Events\EventDispatcher;
use App\Core\Events\StoryDeleted;
use App\Core\Events\StoryRestore;

/**
 * Сервис для работы с историями (бизнес-логика).
 */
class StoryService
{
	private Story $storyModel;
    private Domain $domainModel;
    private ?EventDispatcher $eventDispatcher;

    public function __construct(
        Story $storyModel, 
        Domain $domainModel,
        ?EventDispatcher $eventDispatcher = null
    ) {
        $this->storyModel = $storyModel;
        $this->domainModel = $domainModel;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Создаёт новую историю с полной валидацией.
     *
     * @param array $data Данные истории (title, url, description, tags, user_is_following)
     * @param int $userId ID пользователя
     * @return int ID созданной истории или 0 при ошибке
     */
    public function createStory(array $data, int $userId): int
    {
        // 1. Валидация URL
        if (!empty($data['url']) && !$this->validateUrl($data['url'])) {
            Session::setFlash('error', 'Пожалуйста, укажите корректный URL-адрес.');
            return 0;
        }

        // 2. Валидация заголовка
        if (!$this->validateTitle($data['title'] ?? '')) {
            Session::setFlash('error', 'Заголовок должен содержать как минимум 5 символов.');
            return 0;
        }

        // 3. Проверка забаненных доменов
        $domain = !empty($data['url']) ? parse_url($data['url'], PHP_URL_HOST) : null;
        if (!$this->checkBannedDomain($domain, $userId, $data['url'] ?? '')) {
            return 0; // Домен забанен, ошибка уже установлена в flash
        }

        // 4. Создание истории
        $storyData = [
            'user_id' => $userId,
            'title' => $data['title'],
            'url' => $data['url'] ?? null,
            'domain' => $domain,
            'description' => $data['description'] ?? null,
            'score' => 1,
            'comments_count' => 0,
            'user_is_following' => isset($data['user_is_following']) ? 1 : 0,
        ];

        $storyId = $this->storyModel->create($storyData);

        // 5. Привязка тегов
        if ($storyId > 0 && !empty($data['tags'])) {
            $this->storyModel->syncTags($storyId, $data['tags']);
        }

		// 6. Пересчет hotness после создания и привязки тегов
		if ($storyId > 0) {
			$this->storyModel->recalculateHotness($storyId);
		}

        // 7. Логирование
        \App\Core\Audit::log('story.created', 'Пользователь создал новую публикацию с тегами', 'story');

        return $storyId;
    }

    /**
     * Обновляет существующую историю.
     *
     * @param int $storyId ID истории
     * @param array $data Новые данные
     * @return bool Успешно ли обновлено
     */
    public function updateStory(int $storyId, array $data): bool
    {
        $story = $this->storyModel->find($storyId);
        if (!$story) {
            return false;
        }

        $updateData = [
            'title' => $data['title'] ?? $story['title'],
            'url' => $data['url'] ?? $story['url'],
            'description' => $data['description'] ?? $story['description'],
            'user_is_following' => isset($data['user_is_following']) ? 1 : 0,
        ];

        $this->storyModel->update($storyId, $updateData);

        // Синхронизация тегов
        if (isset($data['tags'])) {
            $this->storyModel->syncTags($storyId, $data['tags']);
        }

        return true;
    }

    /**
     * Проверяет права на редактирование истории.
     *
     * @param array $story Данные истории
     * @param int $userId ID текущего пользователя
     * @return bool Может ли пользователь редактировать
     */
    public function canEditStory(array $story, int $userId): bool
    {
        $isAuthor = (int)$story['user_id'] === $userId;
        $isAdmin = \App\Modules\Auth\Services\Auth::isAdmin();

        return $isAuthor || $isAdmin;
    }

    /**
     * Валидирует URL.
     */
    private function validateUrl(string $url): bool
    {
        return isValidUrl($url);
    }

    /**
     * Валидирует заголовок.
     */
    private function validateTitle(string $title): bool
    {
        $validator = new Validator();
        $minLength = config('validation.title_min_length', 5, 'int');
        return $validator->validate(['title' => $title], ['title' => "required|min:{$minLength}"]);
    }

    /**
     * Проверяет, не забанен ли домен.
     *
     * @return bool true если домен НЕ забанен (можно продолжать), false если забанен
     */
    private function checkBannedDomain(?string $domain, int $userId, string $url): bool
    {
        if (empty($domain)) {
            return true;
        }

        if (!$this->domainModel->isBanned($domain)) {
            return true;
        }

        // Домен забанен - логируем и возвращаем ошибку
        $banInfo = $this->domainModel->getBanInfo($domain);
        $reason = $banInfo['ban_reason'] ?? 'Домен заблокирован администрацией';

        Session::setFlash(
            'error',
            "Публикация отклонена: домен **" . e($domain) . "** заблокирован. Причина: " . e($reason)
        );

        \App\Core\Audit::log('story.rejected_banned_domain', "Попытка публикации с забаненного домена", 'story', [
            'domain' => $domain,
            'user_id' => $userId,
            'url' => $url,
            'reason' => $reason,
        ]);

        return false;
    }
	
   /**
     * Удаляет (скрывает) историю.
     *
     * @param int $storyId ID истории
     * @param int $adminId ID администратора
     * @param string $reason Причина удаления (опционально)
     * @return bool Успешно ли удалено
     */
    public function deleteStory(int $storyId, int $adminId, string $reason = 'История скрыта модератором'): bool
    {
        $story = $this->storyModel->find($storyId);
        if (!$story) {
            Session::setFlash('error', 'Публикация не найдена.');
            return false;
        }

        $this->storyModel->delete($storyId);
        Session::setFlash('success', 'Публикация успешно скрыта из общей ленты.');

        // ✅ Получаем EventDispatcher (через свойство или через app())
        $dispatcher = $this->eventDispatcher ?? app(EventDispatcher::class);
        $dispatcher->dispatch(new StoryDeleted($storyId, $adminId, $reason));

        return true;
    }

    /**
     * Восстанавливает историю.
     *
     * @param int $storyId ID истории
     * @param int $adminId ID администратора
     * @return bool Успешно ли восстановлено
     */
    public function restoreStory(int $storyId, int $adminId): bool
    {
        $story = $this->storyModel->find($storyId, withTrashed: true);
        if (!$story) {
            Session::setFlash('error', 'Публикация не найдена.');
            return false;
        }

        $this->storyModel->restore($storyId);
        Session::setFlash('success', 'Публикация успешно восстановлена в общей ленте.');

        // ✅ Получаем EventDispatcher (через свойство или через app())
        $dispatcher = $this->eventDispatcher ?? app(EventDispatcher::class);
        $dispatcher->dispatch(new StoryRestore($storyId, $adminId));

        return true;
    }
}
