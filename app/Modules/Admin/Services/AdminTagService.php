<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Modules\Tags\Models\Tag;
use App\Modules\Tags\Models\Category;
use App\Modules\Stories\Models\Story;
use App\Core\Session;
use App\Core\Audit;
use App\Core\Validator;
use App\Core\Database;

/**
 * Сервис для административного управления тегами.
 * 
 * ✅ ИЗМЕНЕНО: Все зависимости обязательны и внедряются через конструктор.
 */
class AdminTagService
{
    private Tag $tagModel;
    private Category $categoryModel;
    private Story $storyModel;
    private Session $session;
    private Audit $audit;
    private Validator $validator;
    private Database $db;

    /**
     * ✅ ИЗМЕНЕНО: Все зависимости обязательны
     */
    public function __construct(
        Tag $tagModel,
        Category $categoryModel,
        Story $storyModel,
        Session $session,
        Audit $audit,
        Validator $validator,
        Database $db
    ) {
        $this->tagModel = $tagModel;
        $this->categoryModel = $categoryModel;
        $this->storyModel = $storyModel;
        $this->session = $session;
        $this->audit = $audit;
        $this->validator = $validator;
        $this->db = $db;
    }

    /**
     * Получить все теги.
     */
    public function getAllTags(): array
    {
        return $this->tagModel->getAllTags(true);
    }

    /**
     * Получить тег по ID.
     */
    public function getTagById(int $tagId): ?array
    {
        return $this->tagModel->find($tagId, withTrashed: true);
    }

    /**
     * Создать новый тег.
     *
     * @return int|false ID созданного тега или false при ошибке
     */
    public function createTag(array $data)
    {
        $tagName = strtolower(trim($data['name'] ?? ''));
        $tagSlug = strtolower(trim($data['slug'] ?? ''));
        $description = trim($data['description'] ?? '');
        $isMedia = isset($data['is_media']) ? 1 : 0;
        $categoryId = (int)($data['category_id'] ?? 0);

        // ✅ Валидация slug
        $this->validator->validate(
            ['slug' => $tagSlug], 
            ['slug' => 'required|min:2']
        );
        if (!$this->validator->isValid()) {
            $this->session->flash('error', 'Slug тега должно содержать не менее 2 символов.');
            return false;
        }

        // ✅ Валидация имени
        $this->validator->validate(
            ['name' => $tagName], 
            ['name' => 'required|min:2']
        );
        if (!$this->validator->isValid()) {
            $this->session->flash('error', 'Название тега должно содержать не менее 2 символов.');
            return false;
        }

        // Проверка уникальности
        if ($this->tagModel->exists($tagSlug)) {
            $this->session->flash('error', "Тег '{$tagSlug}' уже присутствует в базе данных.");
            return false;
        }

        // Валидация категории
        if (!$this->categoryModel->find($categoryId)) {
            $this->session->flash('error', 'Выбранная категория не существует.');
            return false;
        }

        $tagId = $this->tagModel->create([
            'name' => $tagName,
            'slug' => $tagSlug,
            'description' => $description,
            'is_media' => $isMedia,
            'category_id' => $categoryId,
        ]);

        // ✅ Используем внедрённый Audit
        $this->audit->log('admin.tag_created', "Администратор создал новый тег #{$tagSlug}", 'admin');

        return $tagId;
    }

    /**
     * Обновить существующий тег.
     *
     * @return bool true если успешно, false при ошибке
     */
    public function updateTag(int $tagId, array $data): bool
    {
        $tag = $this->tagModel->find($tagId);
        if (!$tag) {
            return false;
        }

        $tagName = strtolower(trim($data['name'] ?? ''));
        $tagSlug = strtolower(trim($data['slug'] ?? ''));
        $description = trim($data['description'] ?? '');
        $isMedia = isset($data['is_media']) ? 1 : 0;
        $categoryId = (int)($data['category_id'] ?? 0);
        $hotnessMod = (float)($data['hotness_mod'] ?? 0);

        // ✅ Валидация slug
        $this->validator->validate(
            ['slug' => $tagSlug], 
            ['slug' => 'required|min:2']
        );
        if (!$this->validator->isValid()) {
            $this->session->flash('error', 'Slug тега должно содержать не менее 2 символов.');
            return false;
        }

        // ✅ Валидация имени
        $this->validator->validate(
            ['name' => $tagName], 
            ['name' => 'required|min:2']
        );
        if (!$this->validator->isValid()) {
            $this->session->flash('error', 'Название тега должно содержать не менее 2 символов.');
            return false;
        }

        // Проверка уникальности (исключая текущий тег)
        if ($this->tagModel->exists($tagSlug, $tagId)) {
            $this->session->flash('error', "Имя тега '{$tagSlug}' занято другим элементом.");
            return false;
        }

        // Валидация категории
        if (!$this->categoryModel->find($categoryId)) {
            $this->session->flash('error', 'Выбранная категория не существует.');
            return false;
        }

        $this->tagModel->update($tagId, [
            'name' => $tagName,
            'slug' => $tagSlug,
            'description' => $description,
            'is_media' => $isMedia,
            'category_id' => $categoryId,
            'hotness_mod' => $hotnessMod,
        ]);

        // ✅ Используем $this->db вместо Database::getConnection()
        $stmt = $this->db->prepare("SELECT story_id FROM taggings WHERE tag_id = :tag_id");
        $stmt->execute(['tag_id' => $tagId]);
        $storyIds = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'story_id');

        // ✅ Используем внедрённую Story модель
        foreach ($storyIds as $storyId) {
            $this->storyModel->recalculateHotness((int)$storyId);
        }

        // ✅ Используем внедрённый Audit
        $this->audit->log('admin.tag_updated', "Администратор изменил параметры тега #{$tagSlug}", 'admin');

        return true;
    }

    /**
     * Мягкое удаление (Soft Delete)
     * ✅ ИСПРАВЛЕНО: используем модель Tag через метод delete()
     */
    public function softDeleteTag(int $id): bool
    {
        // Используем встроенный метод модели для soft delete
        return $this->tagModel->delete($id);
    }

    /**
     * Восстановление
     * ✅ ИСПРАВЛЕНО: используем модель Tag через метод restore()
     */
    public function restoreTag(int $id): bool
    {
        // Используем встроенный метод модели для восстановления
        return $this->tagModel->restore($id);
    }
}