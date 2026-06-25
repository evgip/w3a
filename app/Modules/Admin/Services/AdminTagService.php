<?php
declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Modules\Tags\Models\Tag;
use App\Modules\Tags\Models\Category;
use App\Core\Session;
use App\Core\Audit;
use App\Core\Validator;
use App\Core\Database;

/**
 * Сервис для административного управления тегами.
 */
class AdminTagService
{
    private Tag $tagModel;
    private Category $categoryModel;
    
	public function __construct(
		?Tag $tagModel = null,
		?Category $categoryModel = null
	) {
		$this->tagModel = $tagModel ?? new Tag();
		$this->categoryModel = $categoryModel ?? new Category();
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
        return $this->tagModel->getById($tagId);
    }
    
    /**
     * Создать новый тег.
     *
     * @return int|false ID созданного тега или false при ошибке
     */
    public function createTag(array $data)
    {
		$tagName = strtolower(trim($data['name'] ?? ''));
        $tagSlug = strtolower(trim($data['tag'] ?? ''));
        $description = trim($data['description'] ?? '');
        $isMedia = isset($data['is_media']) ? 1 : 0;
        $categoryId = (int)($data['category_id'] ?? 0);
        
        // Валидация имени
        $validator = new Validator();
        if (!$validator->validate(['tag' => $tagSlug], ['tag' => 'required|min:2'])) {
            Session::setFlash('error', 'Имя тега должно содержать не менее 2 символов.');
            return false;
        }
        
        // Проверка уникальности
        if ($this->tagModel->exists($tagSlug)) {
            Session::setFlash('error', "Тег '{$tagSlug}' уже присутствует в базе данных.");
            return false;
        }
        
        // Валидация категории
        if (!$this->categoryModel->getById($categoryId)) {
            Session::setFlash('error', 'Выбранная категория не существует.');
            return false;
        }
        
        $tagId = $this->tagModel->create([
			'name' => $tagName,
            'tag' => $tagSlug,
            'description' => $description,
            'is_media' => $isMedia,
            'category_id' => $categoryId,
        ]);
        
        Audit::log('admin.tag_created', "Администратор создал новый тег #{$tagSlug}", 'admin');
        
        return $tagId;
    }
    
    /**
     * Обновить существующий тег.
     *
     * @return bool true если успешно, false при ошибке
     */
    public function updateTag(int $tagId, array $data): bool
    {
        $tag = $this->tagModel->getById($tagId);
        if (!$tag) {
            return false;
        }
        
		$tagName = strtolower(trim($data['name'] ?? ''));
        $tagSlug = strtolower(trim($data['tag'] ?? ''));
        $description = trim($data['description'] ?? '');
        $isMedia = isset($data['is_media']) ? 1 : 0;
        $categoryId = (int)($data['category_id'] ?? 0);
        $hotnessMod = (float)($data['hotness_mod'] ?? 0);
        
        // Валидация имени
        if (strlen($tagSlug) < 2) {
            Session::setFlash('error', 'Имя тега должно содержать не менее 2 символов.');
            return false;
        }
        
        // Проверка уникальности (исключая текущий тег)
        if ($this->tagModel->exists($tagSlug, $tagId)) {
            Session::setFlash('error', "Имя тега '{$tagSlug}' занято другим элементом.");
            return false;
        }
        
        // Валидация категории
        if (!$this->categoryModel->getById($categoryId)) {
            Session::setFlash('error', 'Выбранная категория не существует.');
            return false;
        }
        
        $this->tagModel->update($tagId, [
			'name' => $tagName,
            'tag' => $tagSlug,
            'description' => $description,
            'is_media' => $isMedia,
            'category_id' => $categoryId,
			'hotness_mod' => $hotnessMod,
        ]);
        
		// Получаем ID всех историй с этим тегом
		$db = Database::getConnection();
		$stmt = $db->prepare("SELECT story_id FROM taggings WHERE tag_id = :tag_id");
		$stmt->execute(['tag_id' => $tagId]);
		$storyIds = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'story_id');

		// Пересчитываем hotness для каждой истории
		$storyModel = new \App\Modules\Stories\Models\Story();
		foreach ($storyIds as $storyId) {
			$storyModel->recalculateHotness($storyId);
		}
		
        Audit::log('admin.tag_updated', "Администратор изменил параметры тега #{$tagSlug}", 'admin');
        
        return true;
    }
	
   // Мягкое удаление (Soft Delete)
    public function softDeleteTag(int $id): bool
    {
        $sql = "UPDATE tags 
                SET deleted_at = NOW() 
                WHERE id = :id AND deleted_at IS NULL";
                
		$db = Database::getConnection();		
        $stmt = $db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    // Восстановление
    public function restoreTag(int $id): bool
    {
        $sql = "UPDATE tags 
                SET deleted_at = NULL 
                WHERE id = :id AND deleted_at IS NOT NULL";
                
		$db = Database::getConnection();		
        $stmt = $db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
}