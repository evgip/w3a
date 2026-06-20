<?php

namespace App\Modules\Tags\Services;

use App\Modules\Tags\Models\TagFilter;
use App\Modules\Tags\Models\Tag;
use App\Core\Auth as AppCoreAuth;

/**
 * Сервис для управления пользовательскими фильтрами тегов.
 * Инкапсулирует бизнес-логику добавления/удаления тегов из фильтров.
 */
class TagFilterService
{
    private TagFilter $filterModel;
    private Tag $tagModel;

    public function __construct(TagFilter $filterModel, Tag $tagModel)
    {
        $this->filterModel = $filterModel;
        $this->tagModel = $tagModel;
    }

    /**
     * Получить данные для страницы управления фильтрами.
     */
    public function getFiltersData(int $userId): array
    {
        return [
            'filters' => $this->filterModel->getUserFilters($userId),
            'allTags' => $this->tagModel->getAllTags(false),
        ];
    }

    /**
     * Добавить тег в фильтры пользователя.
     * @return array Результат операции ['success' => bool, 'message' => string]
     */
    public function addFilter(int $userId, int $tagId): array
    {
        if (!$this->validateInput($userId, $tagId)) {
            return [
                'success' => false,
                'message' => 'Некорректные данные'
            ];
        }

        $result = $this->filterModel->addFilter($userId, $tagId);

        if ($result) {
            return [
                'success' => true,
                'message' => 'Тег добавлен в фильтры'
            ];
        }

        return [
            'success' => false,
            'message' => 'Тег уже в фильтрах'
        ];
    }

    /**
     * Удалить тег из фильтров пользователя.
     * @return array Результат операции ['success' => bool, 'message' => string]
     */
    public function removeFilter(int $userId, int $tagId): array
    {
        if (!$this->validateInput($userId, $tagId)) {
            return [
                'success' => false,
                'message' => 'Некорректные данные'
            ];
        }

        $this->filterModel->removeFilter($userId, $tagId);

        return [
            'success' => true,
            'message' => 'Тег удалён из фильтров'
        ];
    }

    /**
     * Валидация входных данных.
     */
    private function validateInput(int $userId, int $tagId): bool
    {
        return $userId > 0 && $tagId > 0;
    }
}