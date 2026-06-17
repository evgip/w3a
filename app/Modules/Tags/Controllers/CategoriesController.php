<?php

namespace App\Modules\Tags\Controllers;

use App\Core\Controller;
use App\Modules\Tags\Models\Category;
use App\Modules\Tags\Models\Tag;

class CategoriesController extends Controller
{
    /**
     * Страница списка категорий с тегами (GET /categories)
     */
    public function index(): void
    {
        $categoryModel = new Category();
        $categories = $categoryModel->getAllWithTagsCount();

        // Получаем теги для каждой категории
        $tagModel = new Tag();
        $allTags = $tagModel->getAllTags();

        // Группируем теги по category_id
        $tagsByCategory = [];
        foreach ($allTags as $tag) {
            $catId = $tag['category_id'] ?? 0;
            if (!isset($tagsByCategory[$catId])) {
                $tagsByCategory[$catId] = [];
            }
            $tagsByCategory[$catId][] = $tag;
        }

        $this->render('categories/index', [
            'title' => 'Категории тегов',
            'categories' => $categories,
            'tagsByCategory' => $tagsByCategory,
        ]);
    }

    /**
     * Страница конкретной категории (GET /categories/{slug})
     */
    public function show(string $slug): void
    {
        $categoryModel = new Category();
        $category = $categoryModel->findBySlugWithTags($slug);

        if (!$category) {
            $this->redirectWithError('/categories', 'Категория не найдена.');
            return;
        }

        $this->render('categories/show', [
            'title' => $category['name'],
            'category' => $category,
        ]);
    }
}