<?php

namespace App\Modules\Tags\Services;

use App\Modules\Tags\Models\TagFilter;
use App\Modules\Tags\Models\Tag;

/**
 * Сервис для управления пользовательскими фильтрами тегов.
 * Инкапсулирует бизнес-логику добавления/удаления тегов из фильтров.
 */
class TagFilterService
{
    private TagFilter $filterModel;
    private Tag $tagModel;

	public function __construct(
		?TagFilter $filterModel = null,
		?Tag $tagModel = null
	) {
		$this->filterModel = $filterModel ?? new TagFilter();
		$this->tagModel = $tagModel ?? new Tag();
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
	
	public function getTagOpenGraphData(string $tagSlug): array
	{
		$tag = $this->tagModel->getBySlug($tagSlug);
		if (!$tag) return [];

		return [
			'title' => '#' . $tag['tag'] . ' — ' . ($tag['name'] ?? ''),
			'description' => $tag['description'] ?? 'Публикации с тегом #' . $tag['tag'],
			'image' => null,
		];
	}
	
    /**
     * Массив данных для инфы по тегу
     */
	public function getByInfoSlug(string $tagSlug): array
	{
		return $this->tagModel->getBySlug($tagSlug);
	}
}