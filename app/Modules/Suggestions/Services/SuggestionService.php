<?php

declare(strict_types=1);

namespace App\Modules\Suggestions\Services;

use App\Modules\Suggestions\Models\Suggestion;
use App\Modules\Suggestions\Models\ContentLog;
use App\Modules\Stories\Models\Story;
use App\Modules\Stories\Services\StoryValidator;
use App\Modules\Comments\Models\Comment;
use App\Modules\Tags\Models\Tag;
use App\Modules\Tags\Services\TagValidator;
use App\Core\Events\EventDispatcher;
use App\Core\Events\ContentUpdated;
use App\Modules\Auth\Services\Auth;
use Exception;

/**
 * Сервис для работы с предложениями изменений контента.
 * 
 * Вся бизнес-логика здесь, SQL-запросы вынесены в модели.
 */
class SuggestionService
{
    const QUORUM_SIZE = 3;
    const MAX_USER_SUGGESTIONS = 2;

    private Suggestion $suggestionModel;
    private ContentLog $contentLogModel;
    private Story $storyModel;
    private Tag $tagModel;
    private EventDispatcher $eventDispatcher;
    private TagValidator $tagValidator;
    private StoryValidator $storyValidator;

    public function __construct(
        Suggestion $suggestionModel,
        ContentLog $contentLogModel,
        Story $storyModel,
        Tag $tagModel,
        EventDispatcher $eventDispatcher,
        ?TagValidator $tagValidator = null,
        ?StoryValidator $storyValidator = null
    ) {
        $this->suggestionModel = $suggestionModel;
        $this->contentLogModel = $contentLogModel;
        $this->storyModel = $storyModel;
        $this->tagModel = $tagModel;
        $this->eventDispatcher = $eventDispatcher;
        $this->tagValidator = $tagValidator ?? new TagValidator();
        $this->storyValidator = $storyValidator ?? new StoryValidator();
    }
    
    // =========================================================================
    // ПУБЛИЧНЫЕ МЕТОДЫ
    // =========================================================================

    /**
     * Добавить новое предложение
     * 
     * @param string $targetType Тип контента ('Story' или 'Comment')
     * @param int $targetId ID контента
     * @param int $userId ID пользователя, предлагающего изменения
     * @param array $proposedData Предлагаемые изменения (title, tag_ids, text и т.д.)
     * @return int ID созданного предложения
     * @throws Exception
     */
    public function addSuggestion(
        string $targetType,
        int $targetId,
        int $userId,
        array $proposedData
    ): int {
        $isModerator = Auth::isModerator() || Auth::isAdmin();

        // 1. Валидация: автор не может предлагать для своего контента (кроме модераторов)
        if (!$isModerator) {
            $this->ensureNotAuthor($targetType, $targetId, $userId);
        }

        // 2. Проверка лимита (кроме модераторов)
        if (!$isModerator) {
            $this->ensureUserLimit($targetType, $targetId, $userId);
        }

        // 3. Валидация данных
        $this->validateProposedData($targetType, $proposedData);

        // 4. Нормализация JSON для консистентного хеширования
        $normalizedJson = $this->normalizeJson($proposedData);

        // 5. Сохранить предложение
        $suggestionId = $this->suggestionModel->create([
            'target_type' => $targetType,
            'target_id' => $targetId,
            'user_id' => $userId,
            'proposed_data' => $normalizedJson
        ]);

        // 6. Применить изменения (сразу для модератора или по кворуму)
        if ($isModerator) {
            $proposedData = json_decode($normalizedJson, true);
            $this->applyChanges($targetType, $targetId, $proposedData, $userId, true);
        } else {
            $this->checkAndApplyQuorum($targetType, $targetId, $normalizedJson);
        }

        return $suggestionId;
    }

    /**
     * Получить активные предложения для контента
     */
    public function getActiveSuggestions(string $targetType, int $targetId): array
    {
        return $this->suggestionModel->getActiveSuggestions($targetType, $targetId);
    }

    /**
     * Получить лог изменений для контента
     */
    public function getChangeLog(string $targetType, int $targetId, int $limit = 50): array
    {
        return $this->contentLogModel->getChangeLog($targetType, $targetId, $limit);
    }

    /**
     * Получить количество активных предложений от пользователя
     */
    public function getUserActiveSuggestionsCount(string $targetType, int $targetId, int $userId): int
    {
        return $this->suggestionModel->countUserSuggestions($targetType, $targetId, $userId);
    }
    
    // =========================================================================
    // МЕТОДЫ ДЛЯ МОДЕРАТОРОВ
    // =========================================================================

    /**
     * Получить все активные предложения (для страницы модерации).
     * 
     * Дополнительно подгружает названия и URL тегов для каждого предложения.
     */
    public function getAllActiveSuggestions(int $limit = 30, int $offset = 0, string $filter = ''): array
    {
        $suggestions = $this->suggestionModel->getAllActive($limit, $offset, $filter);

        if (empty($suggestions)) {
            return [];
        }

        // Собираем tag_ids ТОЛЬКО из текущей страницы
        $allTagIds = [];
        foreach ($suggestions as $suggestion) {
            $proposedData = json_decode($suggestion['proposed_data'], true);
            if (!empty($proposedData['tag_ids']) && is_array($proposedData['tag_ids'])) {
                $allTagIds = array_merge($allTagIds, $proposedData['tag_ids']);
            }
        }

        $allTagIds = array_unique(array_map('intval', $allTagIds));

        // Получаем полную информацию о тегах (name + tag)
        $tagsDetails = [];
        if (!empty($allTagIds)) {
            $tagsDetails = $this->tagModel->getDetailsByIds($allTagIds);
        }

        // Добавляем информацию о тегах к каждому предложению
        foreach ($suggestions as &$suggestion) {
            $proposedData = json_decode($suggestion['proposed_data'], true);

            if (!empty($proposedData['tag_ids'])) {
                $tagsForSuggestion = [];
                foreach ($proposedData['tag_ids'] as $tagId) {
                    if (isset($tagsDetails[$tagId])) {
                        $tagsForSuggestion[] = $tagsDetails[$tagId];
                    }
                }
                $suggestion['tags_details'] = $tagsForSuggestion;
            } else {
                $suggestion['tags_details'] = [];
            }
        }

        return $suggestions;
    }

    /**
     * Подсчитать количество активных предложений
     */
    public function countAllActiveSuggestions(string $filter = ''): int
    {
        return $this->suggestionModel->countAllActive($filter);
    }

    /**
     * Одобрить предложение (только для модераторов)
     * 
     * Применяет изменения немедленно, удаляет все предложения для этого контента,
     * логирует действие в таблицу moderations.
     */
    public function approveSuggestion(int $suggestionId, int $moderatorId): bool
    {
        if (!Auth::isModerator() && !Auth::isAdmin()) {
            throw new Exception("Только модераторы могут одобрять предложения");
        }

        $suggestion = $this->suggestionModel->find($suggestionId);
        if (!$suggestion) {
            throw new Exception("Предложение не найдено");
        }

        $proposedData = json_decode($suggestion['proposed_data'], true);

        // Применяем изменения (флаг true = действие модератора)
        $this->applyChanges(
            $suggestion['target_type'],
            $suggestion['target_id'],
            $proposedData,
            $moderatorId,
            true
        );

        // Логируем действие модератора
        $this->logModeratorAction($moderatorId, 'approved_suggestion', $suggestion);

        return true;
    }

    /**
     * Отклонить предложение (только для модераторов)
     * 
     * Удаляет предложение без применения изменений.
     * Логирует действие в таблицу moderations.
     */
    public function rejectSuggestion(int $suggestionId, int $moderatorId, string $reason = ''): bool
    {
        if (!Auth::isModerator() && !Auth::isAdmin()) {
            throw new Exception("Только модераторы могут отклонять предложения");
        }

        $suggestion = $this->suggestionModel->find($suggestionId);
        if (!$suggestion) {
            throw new Exception("Предложение не найдено");
        }

        // Удаляем предложение (soft delete)
        $this->suggestionModel->delete($suggestionId);

        // Логируем действие модератора
        $this->logModeratorAction($moderatorId, 'rejected_suggestion', $suggestion, $reason);

        return true;
    }
    
    // =========================================================================
    // ПРИВАТНЫЕ МЕТОДЫ БИЗНЕС-ЛОГИКИ
    // =========================================================================

    /**
     * Проверить кворум и применить изменения
     */
    private function checkAndApplyQuorum(
        string $targetType,
        int $targetId,
        string $normalizedJson
    ): void {
        $matchingCount = $this->suggestionModel->countMatchingSuggestions(
            $targetType,
            $targetId,
            $normalizedJson
        );

        if ($matchingCount >= self::QUORUM_SIZE) {
            $proposedData = json_decode($normalizedJson, true);
            $this->applyChanges($targetType, $targetId, $proposedData);
        }
    }

    /**
     * Применить изменения к контенту
     */
    private function applyChanges(
        string $targetType,
        int $targetId,
        array $proposedData,
        int $actorId = null,
        bool $isModeratorAction = false
    ): void {
        $model = $this->findModel($targetType);
        $currentData = $model->find($targetId);

        if (!$currentData) {
            throw new Exception("Target not found");
        }

        // Страховочная валидация перед применением
        if (isset($proposedData['tag_ids'])) {
            $validation = $this->tagValidator->validateForSuggestion($proposedData['tag_ids']);
            if (!$validation['valid']) {
                throw new Exception("Cannot apply suggestion: " . $validation['error']);
            }
        }

        // Собираем старые значения для лога
        $oldValues = $this->collectOldValues($targetType, $targetId, $proposedData);

        // Применяем изменения
        foreach ($proposedData as $key => $value) {
            if ($key === 'tag_ids') {
                $this->storyModel->syncTags($targetId, $value);
            } else {
                $model->update($targetId, [$key => $value]);
            }
        }

        // Диспатчим событие
        $this->dispatchContentEvent(new ContentUpdated(
            $targetType,
            $targetId,
            $oldValues,
            $proposedData,
            $isModeratorAction ? 'moderator' : 'community_quorum'
        ));

        // Записываем в лог
        $logText = $this->formatLogText($oldValues, $proposedData, $isModeratorAction);
        $this->contentLogModel->create([
            'target_type' => $targetType,
            'target_id' => $targetId,
            'actor_id' => $actorId,
            'action_text' => $logText,
            // is_community_action: 0 = действие модератора, 1 = действие сообщества
            'is_community_action' => $isModeratorAction ? 0 : 1
        ]);

        // Удаляем все предложения для этого контента
        $this->suggestionModel->deleteAllForTarget($targetType, $targetId);
    }

    /**
     * Собрать старые значения для лога
     */
    private function collectOldValues(string $targetType, int $targetId, array $proposedData): array
    {
        $oldValues = [];
        $model = $this->findModel($targetType);
        $currentData = $model->find($targetId);

        foreach (array_keys($proposedData) as $key) {
            if ($key === 'tag_ids') {
                // Используем существующий метод из модели Story
                $oldTagIds = $this->storyModel->getStoryTagIds($targetId);
                $oldValues['tags'] = $this->tagModel->getNamesByIds($oldTagIds);
            } else {
                $oldValues[$key] = $currentData[$key] ?? null;
            }
        }

        return $oldValues;
    }

    /**
     * Логировать действие модератора в таблицу moderations.
     * 
     * @param int    $moderatorId ID модератора, выполнившего действие
     * @param string $action      Тип действия (approved_suggestion, rejected_suggestion)
     * @param array  $suggestion  Данные предложения
     * @param string $reason      Причина (опционально)
     */
    private function logModeratorAction(
        int $moderatorId,
        string $action,
        array $suggestion,
        string $reason = ''
    ): void {
        try {
            // Получаем данные модератора
            $userModel = new \App\Modules\Users\Models\User();
            $moderator = $userModel->getUser($moderatorId);

            // Получаем IP из запроса
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            $modLog = new \App\Modules\Moderations\Models\Moderation();
            $modLog->create([
                'user_id'     => $moderatorId,
                'username'    => $moderator['username'] ?? 'Unknown',
                'role'        => $moderator['role'] ?? 'moderator',
                'ip_address'  => $ipAddress,
                'action'      => 'moderation.' . $action,       // например: moderation.approve_suggestion
                'description' => $reason ?: "Модератор {$action} предложение #{$suggestion['id']}",
                'category'    => 'moderation',
                'payload'     => json_encode([
                    'target_type' => strtolower($suggestion['target_type']),
                    'target_id'   => (int) $suggestion['target_id'],
                    'suggestion_id' => (int) $suggestion['id'],
                ], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            // Логируем в файл, чтобы не терять информацию
            \App\Core\Logger::error('Failed to write moderation log: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Флеш-сообщение пользователю показывать не стоит — 
            // одобрение уже прошло, а лог — это служебная информация
        }
    }

    /**
     * Форматировать текст для лога
     */
    private function formatLogText(array $oldValues, array $newValues, bool $isModeratorAction = false): string
    {
        $parts = [];

        foreach ($newValues as $key => $newValue) {
            if ($key === 'tag_ids') {
                $oldTags = $oldValues['tags'] ?? [];
                $newTags = $this->tagModel->getNamesByIds($newValue);
                $parts[] = "изменены теги с [" . implode(', ', $oldTags) . "] на [" . implode(', ', $newTags) . "]";
            } else {
                $oldValue = $oldValues[$key] ?? 'null';
                $parts[] = "изменил {$key} с \"{$oldValue}\" на \"{$newValue}\"";
            }
        }

        $source = $isModeratorAction ? "(модератор)" : "(через кворум сообщества)";
        return implode(", ", $parts) . " {$source}";
    }

    /**
     * Найти модель по типу
     */
    private function findModel(string $targetType)
    {
        return match ($targetType) {
            'Story' => $this->storyModel,
            'Comment' => new Comment(),
            default => throw new Exception("Invalid target type: {$targetType}")
        };
    }

    /**
     * Проверить, что пользователь не автор
     */
    private function ensureNotAuthor(string $targetType, int $targetId, int $userId): void
    {
        $model = $this->findModel($targetType);
        $record = $model->find($targetId);

        if (!$record) {
            throw new Exception("Target not found");
        }

        if ((int) $record['user_id'] === $userId) {
            throw new Exception("Authors cannot suggest changes to their own content");
        }
    }

    /**
     * Проверить лимит предложений от пользователя
     */
    private function ensureUserLimit(string $targetType, int $targetId, int $userId): void
    {
        $count = $this->suggestionModel->countUserSuggestions($targetType, $targetId, $userId);

        if ($count >= self::MAX_USER_SUGGESTIONS) {
            throw new Exception("Вы уже отправили {$count} предложения. Дождитесь их рассмотрения.");
        }
    }

    /**
     * Валидация данных
     */
    private function validateProposedData(string $targetType, array $proposedData): void
    {
        if (empty($proposedData)) {
            throw new Exception("Proposed data cannot be empty");
        }

        $allowedKeys = match ($targetType) {
            'Story' => ['title', 'url', 'description', 'tag_ids'],
            'Comment' => ['text'],
            default => throw new Exception("Invalid target type: {$targetType}")
        };

        foreach (array_keys($proposedData) as $key) {
            if (!in_array($key, $allowedKeys)) {
                throw new Exception("Invalid field for {$targetType} suggestion: {$key}");
            }
        }

        // Валидация количества тегов
        if ($targetType === 'Story') {
            $validation = $this->storyValidator->validateForSuggestion($proposedData);
            if (!$validation['valid']) {
                throw new Exception(implode(' ', $validation['errors']));
            }
        }
    }
    
    // =========================================================================
    // НОРМАЛИЗАЦИЯ JSON
    // =========================================================================

    /**
     * Нормализовать JSON для консистентного хеширования
     * Сортирует ключи рекурсивно, чтобы {"b":1,"a":2} и {"a":2,"b":1} давали одинаковый результат
     */
    private function normalizeJson(array $data): string
    {
        $this->sortArrayRecursive($data);
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Рекурсивная сортировка массива по ключам
     */
    private function sortArrayRecursive(array &$data): void
    {
        ksort($data);
        foreach ($data as &$value) {
            if (is_array($value)) {
                if ($this->isAssociative($value)) {
                    $this->sortArrayRecursive($value);
                } else {
                    // Для индексных массивов (теги) - сортируем значения
                    sort($value);
                }
            }
        }
    }

    /**
     * Проверить, ассоциативный ли массив
     */
    private function isAssociative(array $arr): bool
    {
        if (empty($arr)) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Безопасно диспатчит событие через EventDispatcher.
     * 
     * Если EventDispatcher не был передан в конструктор, событие не будет отправлено.
     */
    private function dispatchContentEvent(\App\Core\Events\Event $event): void
    {
        if ($this->eventDispatcher === null) {
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }
}
