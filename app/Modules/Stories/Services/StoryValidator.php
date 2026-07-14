<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

use App\Modules\Tags\Services\TagValidator;
use App\Modules\Origins\Models\Domain;

/**
 * Валидатор данных истории.
 * 
 * Используется в StoryService (create/update) и SuggestionService.
 * Отвечает только за проверку данных, не работает с БД напрямую.
 */
class StoryValidator
{
    private TagValidator $tagValidator;
    private Domain $domainModel;

    /**
     * Конструктор с инъекцией зависимостей.
     * 
     * @param TagValidator $tagValidator Валидатор тегов
     * @param Domain $domainModel Модель доменов
     */
    public function __construct(
        TagValidator $tagValidator,
        Domain $domainModel
    ) {
        $this->tagValidator = $tagValidator;
        $this->domainModel = $domainModel;
    }

    /**
     * Полная валидация данных истории.
     * 
     * @param array $data Данные для проверки
     * @param bool $isUpdate true если это обновление (некоторые поля опциональны)
     * @return array ['valid' => bool, 'errors' => string[]]
     */
    public function validate(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        // 1. Валидация заголовка
        $titleError = $this->validateTitle($data['title'] ?? '', $isUpdate);
        if ($titleError) {
            $errors[] = $titleError;
        }

        // 2. Валидация URL
        if (!empty($data['url'])) {
            $urlError = $this->validateUrl($data['url']);
            if ($urlError) {
                $errors[] = $urlError;
            }

            // 3. Проверка забаненных доменов
            $domainError = $this->validateDomain($data['url']);
            if ($domainError) {
                $errors[] = $domainError;
            }
        }

        // 4. Валидация тегов
        if (isset($data['tags'])) {
            $tagValidation = $this->tagValidator->validateForStory($data['tags']);
            if (!$tagValidation['valid']) {
                $errors[] = $tagValidation['error'];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Валидация заголовка.
     * 
     * @param string $title Заголовок
     * @param bool $isUpdate true если обновление (заголовок опционален)
     * @return string|null Сообщение об ошибке или null
     */
    public function validateTitle(string $title, bool $isUpdate = false): ?string
    {
        $minLength = config('validation.title_min_length', 5, 'int');

        if (empty($title)) {
            return $isUpdate ? null : 'Заголовок обязателен.';
        }

        if (mb_strlen($title) < $minLength) {
            return "Заголовок должен содержать как минимум {$minLength} символов.";
        }

        $maxLength = config('validation.title_max_length', 150, 'int');
        if (mb_strlen($title) > $maxLength) {
            return "Заголовок слишком длинный. Максимум {$maxLength} символов.";
        }

        return null;
    }

    /**
     * Валидация URL.
     * 
     * @param string $url URL для проверки
     * @return string|null Сообщение об ошибке или null
     */
    public function validateUrl(string $url): ?string
    {
        if (!isValidUrl($url)) {
            return 'Пожалуйста, укажите корректный URL-адрес.';
        }

        return null;
    }

    /**
     * Проверка забаненных доменов.
     * 
     * @param string $url URL для проверки
     * @return string|null Сообщение об ошибке или null
     */
    public function validateDomain(string $url): ?string
    {
        $domain = parse_url($url, PHP_URL_HOST);

        if (empty($domain)) {
            return null;
        }

        if (!$this->domainModel->isBanned($domain)) {
            return null;
        }

        $banInfo = $this->domainModel->getBanInfo($domain);
        $reason = $banInfo['ban_reason'] ?? 'Домен заблокирован администрацией';

        return "Публикация отклонена: домен **{$domain}** заблокирован. Причина: {$reason}";
    }

    /**
     * Валидация для предложения (Suggestion).
     * Проверяет только те поля, которые переданы.
     * 
     * @param array $proposedData Предлагаемые изменения
     * @return array ['valid' => bool, 'errors' => string[]]
     */
    public function validateForSuggestion(array $proposedData): array
    {
        $errors = [];

        // Заголовок (если предложен)
        if (isset($proposedData['title'])) {
            $titleError = $this->validateTitle($proposedData['title'], false);
            if ($titleError) {
                $errors[] = $titleError;
            }
        }

        // URL (если предложен)
        if (!empty($proposedData['url'])) {
            $urlError = $this->validateUrl($proposedData['url']);
            if ($urlError) {
                $errors[] = $urlError;
            }

            $domainError = $this->validateDomain($proposedData['url']);
            if ($domainError) {
                $errors[] = $domainError;
            }
        }

        // Теги (если предложены)
        if (isset($proposedData['tag_ids'])) {
            $tagValidation = $this->tagValidator->validateForSuggestion($proposedData['tag_ids']);
            if (!$tagValidation['valid']) {
                $errors[] = $tagValidation['error'];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
