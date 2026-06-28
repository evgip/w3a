<?php

declare(strict_types=1);

namespace App\Modules\Tags\Services;

/**
 * Валидатор для работы с тегами.
 * 
 * Используется в StoryService, SuggestionService и других местах,
 * где требуется проверка массива ID тегов.
 */
class TagValidator
{
    /**
     * Проверяет массив ID тегов на валидность.
     *
     * @param array $tagIds Массив ID тегов
     * @param int $minTags Минимальное количество (0 = без минимума)
     * @param int $maxTags Максимальное количество
     * @return array ['valid' => bool, 'error' => string|null, 'count' => int]
     */
    public function validate(array $tagIds, int $minTags = 1, int $maxTags = 7): array
    {
        $count = count($tagIds);
        
        // Проверка минимума
        if ($minTags > 0 && $count < $minTags) {
            return [
                'valid' => false,
                'error' => "Минимум {$minTags} тег(ов). Сейчас выбрано: {$count}.",
                'count' => $count,
            ];
        }
        
        // Проверка максимума
        if ($count > $maxTags) {
            return [
                'valid' => false,
                'error' => "Максимум {$maxTags} тег(ов). Сейчас выбрано: {$count}.",
                'count' => $count,
            ];
        }
        
        // Проверка типов ID
        foreach ($tagIds as $tagId) {
            if (!is_int($tagId) && !ctype_digit((string) $tagId)) {
                return [
                    'valid' => false,
                    'error' => 'Некорректный ID тега.',
                    'count' => $count,
                ];
            }
        }
        
        return [
            'valid' => true,
            'error' => null,
            'count' => $count,
        ];
    }
    
    /**
     * Валидация для истории (создание/редактирование).
     * Читает лимиты из конфига.
     */
    public function validateForStory(array $tagIds): array
    {
        $min = config('validation.story.min_tags', 1, 'int');
        $max = config('validation.story.max_tags', 4, 'int');
        
        return $this->validate($tagIds, $min, $max);
    }
    
    /**
     * Валидация для предложения (Suggestions).
     * Читает лимиты из конфига.
     */
    public function validateForSuggestion(array $tagIds): array
    {
        $max = config('validation.suggestion.max_tags', 4, 'int');
        
        return $this->validate($tagIds, 1, $max);
    }
}