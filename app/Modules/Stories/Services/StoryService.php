<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use App\Modules\Stories\Models\Story;
use App\Modules\Origins\Models\Domain;
use App\Core\Session;
use App\Core\Audit;
use App\Core\Validator;
use App\Core\Events\EventDispatcher;
use App\Core\Events\StoryDeleted;
use App\Core\Events\StoryRestore;

/**
 * Сервис для работы с историями (бизнес-логика).
 * 
 * Все зависимости обязательны и внедряются через конструктор.
 */
class StoryService
{
    private Story $storyModel;
    private Domain $domainModel;
    private StoryValidator $storyValidator;
    private Session $session;
    private Audit $audit;
    private Validator $validator;
    private ?EventDispatcher $eventDispatcher;

    /**
     * 7 зависимостей, все обязательны (кроме EventDispatcher)
     */
    public function __construct(
        Story $storyModel,
        Domain $domainModel,
        StoryValidator $storyValidator,
        Session $session,
        Audit $audit,
        Validator $validator,
        ?EventDispatcher $eventDispatcher = null
    ) {
        $this->storyModel = $storyModel;
        $this->domainModel = $domainModel;
        $this->storyValidator = $storyValidator;
        $this->session = $session;
        $this->audit = $audit;
        $this->validator = $validator;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Создаёт новую историю с полной валидацией.
     */
    public function createStory(array $data, int $userId): int
    {
        // 1. Централизованная валидация через StoryValidator
        $validation = $this->storyValidator->validate($data, false);
        if (!$validation['valid']) {
            $this->session->flash('error', implode(' ', $validation['errors']));
            return 0;
        }

        // 2. Создание истории
        $domain = !empty($data['url']) ? parse_url($data['url'], PHP_URL_HOST) : null;
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

        // 3. Привязка тегов
        if ($storyId > 0 && !empty($data['tags'])) {
            $this->storyModel->syncTags($storyId, $data['tags']);
        }

        // 4. Пересчет hotness после создания и привязки тегов
        if ($storyId > 0) {
            $this->storyModel->recalculateHotness($storyId);
        }

        // 5. Логирование
        $this->audit->log('story.created', 'Пользователь создал новую публикацию с тегами', 'story');

        return $storyId;
    }

    /**
     * Обновляет существующую историю.
     */
    public function updateStory(int $storyId, array $data): bool
    {
        $story = $this->storyModel->find($storyId);
        if (!$story) {
            return false;
        }

        // 1. Централизованная валидация через StoryValidator
        $validation = $this->storyValidator->validate($data, true);
        if (!$validation['valid']) {
            $this->session->flash('error', implode(' ', $validation['errors']));
            return false;
        }

        // 2. Обновление данных
        $domain = !empty($data['url']) ? parse_url($data['url'], PHP_URL_HOST) : null;
        $updateData = [
            'title' => $data['title'] ?? $story['title'],
            'url' => $data['url'] ?? $story['url'],
            'description' => $data['description'] ?? $story['description'],
            'domain' => $domain,
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
        $minLength = config('validation.title_min_length', 5, 'int');
        return $this->validator->validate(
            ['title' => $title], 
            ['title' => "required|min:{$minLength}"]
        );
    }

    /**
     * Проверяет, не забанен ли домен.
     */
    private function checkBannedDomain(?string $domain, int $userId, string $url): bool
    {
        if (empty($domain)) {
            return true;
        }

        if (!$this->domainModel->isBanned($domain)) {
            return true;
        }

        $banInfo = $this->domainModel->getBanInfo($domain);
        $reason = $banInfo['ban_reason'] ?? 'Домен заблокирован администрацией';

        $this->session->flash(
            'error',
            "Публикация отклонена: домен **" . e($domain) . "** заблокирован. Причина: " . e($reason)
        );

        $this->audit->log('story.rejected_banned_domain', "Попытка публикации с забаненного домена", 'story', [
            'domain' => $domain,
            'user_id' => $userId,
            'url' => $url,
            'reason' => $reason,
        ]);

        return false;
    }

    /**
     * Удаляет (скрывает) историю.
     */
    public function deleteStory(int $storyId, int $adminId, string $reason = 'История скрыта модератором'): bool
    {
        $story = $this->storyModel->find($storyId);
        if (!$story) {
            $this->session->flash('error', 'Публикация не найдена.');
            return false;
        }

        $this->storyModel->delete($storyId);
        $this->session->flash('success', 'Публикация успешно скрыта из общей ленты.');

        if ($this->eventDispatcher !== null) {
            $this->eventDispatcher->dispatch(new StoryDeleted($storyId, $adminId, $reason));
        }

        return true;
    }

    /**
     * Восстанавливает историю.
     */
    public function restoreStory(int $storyId, int $adminId): bool
    {
        $story = $this->storyModel->find($storyId, withTrashed: true);
        if (!$story) {
            $this->session->flash('error', 'Публикация не найдена.');
            return false;
        }

        $this->storyModel->restore($storyId);
        $this->session->flash('success', 'Публикация успешно восстановлена в общей ленте.');

        if ($this->eventDispatcher !== null) {
            $this->eventDispatcher->dispatch(new StoryRestore($storyId, $adminId));
        }

        return true;
    }
}