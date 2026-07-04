<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Modules\Tags\Models\Category;
use App\Core\Session;
use App\Core\Audit;

/**
 * Сервис для административного управления категориями тегов.
 * 
 * ✅ ИЗМЕНЕНО: Все зависимости обязательны и внедряются через конструктор.
 */
class AdminCategoryService
{
    private Category $categoryModel;
    private Session $session;
    private Audit $audit;

    public function __construct(
        Category $categoryModel,
        Session $session,
        Audit $audit
    ) {
        $this->categoryModel = $categoryModel;
        $this->session = $session;
        $this->audit = $audit;
    }

    public function getCategoriesList(): array
    {
        return $this->categoryModel->getAdminCategoriesList();
    }

    public function getCategoryById(int $categoryId): ?array
    {
        return $this->categoryModel->find($categoryId, withTrashed: true);
    }

    public function createCategory(array $data)
    {
        $name = trim($data['name'] ?? '');
        $slug = strtolower(trim($data['slug'] ?? ''));
        $description = trim($data['description'] ?? '');
        $sortOrder = (int)($data['sort_order'] ?? 0);

        if (strlen($name) < 2) {
            $this->session->flash('error', 'Название категории должно содержать не менее 2 символов.');
            return false;
        }

        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            $this->session->flash('error', 'Slug должен содержать только латиницу в нижнем регистре, цифры и дефис.');
            return false;
        }

        if ($this->categoryModel->slugExists($slug)) {
            $this->session->flash('error', "Категория с slug '{$slug}' уже существует.");
            return false;
        }

        $categoryId = $this->categoryModel->createCategory([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'sort_order' => $sortOrder,
        ]);

        $this->audit->log('admin.category_created', "Администратор создал категорию '{$name}' (slug: {$slug})", 'admin');

        return $categoryId;
    }

    public function updateCategory(int $categoryId, array $data): bool
    {
        $category = $this->categoryModel->find($categoryId);
        if (!$category) {
            $this->session->flash('error', 'Категория не найдена.');
            return false;
        }

        $name = trim($data['name'] ?? '');
        $slug = strtolower(trim($data['slug'] ?? ''));
        $description = trim($data['description'] ?? '');
        $sortOrder = (int)($data['sort_order'] ?? 0);

        if (strlen($name) < 2) {
            $this->session->flash('error', 'Название категории должно содержать не менее 2 символов.');
            return false;
        }

        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            $this->session->flash('error', 'Slug должен содержать только латиницу в нижнем регистре, цифры и дефис.');
            return false;
        }

        if ($this->categoryModel->slugExists($slug, $categoryId)) {
            $this->session->flash('error', "Slug '{$slug}' уже используется другой категорией.");
            return false;
        }

        $this->categoryModel->updateCategory($categoryId, [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'sort_order' => $sortOrder,
        ]);

        $this->audit->log('admin.category_updated', "Администратор обновил категорию '{$name}' (ID: {$categoryId})", 'admin');

        return true;
    }

    public function deleteCategory(int $categoryId): bool
    {
        $category = $this->categoryModel->find($categoryId);
        if (!$category) {
            $this->session->flash('error', 'Категория не найдена.');
            return false;
        }

        if ($this->categoryModel->hasTags($categoryId)) {
            $this->session->flash('error', 'Нельзя удалить категорию, содержащую теги. Сначала перенесите теги в другую категорию.');
            return false;
        }

        if ($this->categoryModel->deleteCategory($categoryId)) {
            $this->audit->log(
                'admin.category_deleted',
                "Администратор удалил категорию '{$category['name']}' (ID: {$categoryId})",
                'admin'
            );
            return true;
        }

        return false;
    }
}