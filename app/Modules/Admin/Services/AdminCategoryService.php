<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Modules\Tags\Models\Category;
use App\Core\Session;
use App\Core\Audit;

/**
 * Сервис для административного управления категориями тегов.
 */
class AdminCategoryService
{
    private Category $categoryModel;

    public function __construct(?Category $categoryModel = null)
    {
        $this->categoryModel = $categoryModel ?? new Category();
    }

    /**
     * Получить список категорий для админки.
     */
    public function getCategoriesList(): array
    {
        return $this->categoryModel->getAdminCategoriesList();
    }

    /**
     * Получить категорию по ID.
     */
    public function getCategoryById(int $categoryId): ?array
    {
        return $this->categoryModel->find($categoryId, withTrashed: true);
    }

    /**
     * Создать новую категорию.
     *
     * @return int|false ID созданной категории или false при ошибке
     */
    public function createCategory(array $data)
    {
        $name = trim($data['name'] ?? '');
        $slug = strtolower(trim($data['slug'] ?? ''));
        $description = trim($data['description'] ?? '');
        $sortOrder = (int)($data['sort_order'] ?? 0);

        // Валидация
        if (strlen($name) < 2) {
            Session::setFlash('error', 'Название категории должно содержать не менее 2 символов.');
            return false;
        }

        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            Session::setFlash('error', 'Slug должен содержать только латиницу в нижнем регистре, цифры и дефис.');
            return false;
        }

        if ($this->categoryModel->slugExists($slug)) {
            Session::setFlash('error', "Категория с slug '{$slug}' уже существует.");
            return false;
        }

        $categoryId = $this->categoryModel->createCategory([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'sort_order' => $sortOrder,
        ]);

        Audit::log('admin.category_created', "Администратор создал категорию '{$name}' (slug: {$slug})", 'admin');

        return $categoryId;
    }

    /**
     * Обновить существующую категорию.
     */
    public function updateCategory(int $categoryId, array $data): bool
    {
        $category = $this->categoryModel->find($categoryId);
        if (!$category) {
            Session::setFlash('error', 'Категория не найдена.');
            return false;
        }

        $name = trim($data['name'] ?? '');
        $slug = strtolower(trim($data['slug'] ?? ''));
        $description = trim($data['description'] ?? '');
        $sortOrder = (int)($data['sort_order'] ?? 0);

        // Валидация
        if (strlen($name) < 2) {
            Session::setFlash('error', 'Название категории должно содержать не менее 2 символов.');
            return false;
        }

        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            Session::setFlash('error', 'Slug должен содержать только латиницу в нижнем регистре, цифры и дефис.');
            return false;
        }

        if ($this->categoryModel->slugExists($slug, $categoryId)) {
            Session::setFlash('error', "Slug '{$slug}' уже используется другой категорией.");
            return false;
        }

        $this->categoryModel->updateCategory($categoryId, [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'sort_order' => $sortOrder,
        ]);

        Audit::log('admin.category_updated', "Администратор обновил категорию '{$name}' (ID: {$categoryId})", 'admin');

        return true;
    }

    /**
     * Удалить категорию.
     */
    public function deleteCategory(int $categoryId): bool
    {
        $category = $this->categoryModel->find($categoryId);
        if (!$category) {
            Session::setFlash('error', 'Категория не найдена.');
            return false;
        }

        // Проверяем наличие тегов
        if ($this->categoryModel->hasTags($categoryId)) {
            Session::setFlash('error', 'Нельзя удалить категорию, содержащую теги. Сначала перенесите теги в другую категорию.');
            return false;
        }

        if ($this->categoryModel->deleteCategory($categoryId)) {
            Audit::log(
                'admin.category_deleted',
                "Администратор удалил категорию '{$category['name']}' (ID: {$categoryId})",
                'admin'
            );
            return true;
        }

        return false;
    }
}
