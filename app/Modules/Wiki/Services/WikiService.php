<?php

declare(strict_types=1);

namespace App\Modules\Wiki\Services;

use App\Modules\Wiki\Models\WikiPage;
use App\Modules\Wiki\Models\WikiRevision;
use App\Core\Session;
use App\Core\Audit;
use App\Core\Events\EventDispatcher;
use App\Core\Events\WikiPageCreated;
use App\Core\Events\WikiPageUpdated;
use App\Core\Events\WikiPageDeleted;

/**
 * Сервис для работы с wiki страницами (бизнес-логика).
 */
class WikiService
{
    private WikiPage $wikiPage;
    private WikiRevision $wikiRevision;
    private ?EventDispatcher $eventDispatcher;

    public function __construct(
        WikiPage $wikiPage,
        WikiRevision $wikiRevision,
        ?EventDispatcher $eventDispatcher = null
    ) {
        $this->wikiPage = $wikiPage;
        $this->wikiRevision = $wikiRevision;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Создаёт новую wiki страницу с валидацией.
     */
    public function createPage(array $data, int $userId): int
    {
        // 1. Валидация
        if (empty($data['title'])) {
            Session::setFlash('error', 'Заголовок не может быть пустым');
            return 0;
        }

        if (empty($data['content'])) {
            Session::setFlash('error', 'Содержимое не может быть пустым');
            return 0;
        }

        if (mb_strlen($data['title']) < 3) {
            Session::setFlash('error', 'Заголовок слишком короткий (минимум 3 символа)');
            return 0;
        }

        // 2. Генерация или валидация slug
        $slug = $this->prepareSlug($data['slug'] ?? '', $data['title']);

        if (!$slug) {
            return 0; // Ошибка уже установлена в prepareSlug
        }

        // Проверка уникальности slug
        if ($this->wikiPage->slugExists($slug, (int)$data['tag_id'])) {
            Session::setFlash('error', 'Страница с таким URL уже существует. Попробуйте другой slug.');
            return 0;
        }

        // 3. Рендеринг markdown
        $renderedContent = markdown($data['content']);

        // 4. Если страница помечена как основная - сбросить флаг у других
        if (!empty($data['is_primary']) && isset($data['tag_id'])) {
            $this->wikiPage->resetPrimaryFlag($data['tag_id']);
        }

        // 5. Создание страницы
        $pageId = $this->wikiPage->create([
            'tag_id' => $data['tag_id'] ?? null,
            'title' => $data['title'],
            'slug' => $slug,
            'content' => $data['content'],
            'rendered_content' => $renderedContent,
            'author_id' => $userId,
            'is_primary' => $data['is_primary'] ?? 0,
            'status' => $data['status'] ?? 'published'
        ]);

        if ($pageId > 0) {
            // 6. Создание первой ревизии
            $this->wikiRevision->create([
                'wiki_page_id' => $pageId,
                'revision_number' => 1,
                'content' => $data['content'],
                'edit_summary' => 'Первоначальное создание',
                'user_id' => $userId
            ]);

            // 7. Логирование
            \App\Core\Audit::log('wiki.created', 'Пользователь создал wiki страницу', 'wiki', [
                'page_id' => $pageId,
                'tag_id' => $data['tag_id'] ?? null,
                'user_id' => $userId
            ]);

            // 8. Диспатч события 
            if ($this->eventDispatcher) {
                $this->eventDispatcher->dispatch(new WikiPageCreated(
                    $pageId,
                    $userId,
                    $data['tag_id'] ?? null,
                    $data['title']
                ));
            }
        }

        return $pageId;
    }

    /**
     * Обновляет существующую wiki страницу.
     */
    public function updatePage(int $pageId, array $data, int $userId): bool
    {
        $page = $this->wikiPage->find($pageId);
        if (!$page) {
            Session::setFlash('error', 'Wiki страница не найдена');
            return false;
        }

        // 1. Валидация
        if (empty($data['title'])) {
            Session::setFlash('error', 'Заголовок не может быть пустым');
            return false;
        }

        if (empty($data['content'])) {
            Session::setFlash('error', 'Содержимое не может быть пустым');
            return false;
        }

        if (mb_strlen($data['title']) < 3) {
            Session::setFlash('error', 'Заголовок слишком короткий (минимум 3 символа)');
            return false;
        }

        // 2. Генерация или валидация slug
        $slug = $this->prepareSlug($data['slug'] ?? '', $data['title']);

        if (!$slug) {
            return false; // Ошибка уже установлена в prepareSlug
        }

		// Получаем текущую страницу для tag_id
		$currentPage = $this->wikiPage->find($pageId);
		if (!$currentPage) {
			return false;
		}
		
		// Проверка уникальности slug в пределах тега (исключая текущую страницу)
		if (isset($data['slug']) && $data['slug'] !== $currentPage['slug']) {
			if ($this->wikiPage->slugExists($data['slug'], (int)$currentPage['tag_id'], $pageId)) {
				Session::setFlash('error', 'Страница с таким URL уже существует в этом теге.');
				return false;
			}
		}

        // 3. Рендеринг markdown
        $renderedContent = markdown($data['content']);

        // 4. Если страница помечена как основная - сбросить флаг у других
        if (!empty($data['is_primary']) && $page['tag_id']) {
            $this->wikiPage->resetPrimaryFlag($page['tag_id']);
        }

        // 5. Обновление страницы
        $this->wikiPage->update($pageId, [
            'title' => $data['title'],
            'slug' => $slug,
            'content' => $data['content'],
            'rendered_content' => $renderedContent,
            'is_primary' => $data['is_primary'] ?? 0,
            'status' => $data['status'] ?? 'published'
        ]);

        // 6. Создание новой ревизии
        $revisionNumber = $this->wikiRevision->getNextRevisionNumber($pageId);
        $this->wikiRevision->create([
            'wiki_page_id' => $pageId,
            'revision_number' => $revisionNumber,
            'content' => $data['content'],
            'edit_summary' => $data['edit_summary'] ?? '',
            'user_id' => $userId
        ]);

        // 7. Логирование
        \App\Core\Audit::log('wiki.updated', 'Пользователь обновил wiki страницу', 'wiki', [
            'page_id' => $pageId,
            'user_id' => $userId,
            'revision' => $revisionNumber
        ]);

        // 8. Диспатч события
        if ($this->eventDispatcher) {
            $this->eventDispatcher->dispatch(new WikiPageUpdated(
                $pageId,
                $userId,
                $revisionNumber,
                $data['edit_summary'] ?? null
            ));
        }

        return true;
    }

    /**
     * Подготавливает slug: валидирует пользовательский или генерирует из заголовка.
     *
     * @param string $userSlug Slug от пользователя (может быть пустым)
     * @param string $title Заголовок для автогенерации
     * @return string|null Готовый slug или null при ошибке
     */
    private function prepareSlug(string $userSlug, string $title): ?string
    {
        if (!empty($userSlug)) {
            // Валидация пользовательского slug
            $slug = $this->sanitizeSlug($userSlug);

            if (empty($slug)) {
                Session::setFlash('error', 'URL может содержать только латинские буквы, цифры и дефисы');
                return null;
            }

            if (strlen($slug) < 3) {
                Session::setFlash('error', 'URL слишком короткий (минимум 3 символа)');
                return null;
            }

            if (strlen($slug) > 200) {
                Session::setFlash('error', 'URL слишком длинный (максимум 200 символов)');
                return null;
            }

            return $slug;
        }

        // Автогенерация из заголовка через транслитерацию
        return $this->transliterate($title);
    }

    /**
     * Очищает slug от недопустимых символов.
     */
    private function sanitizeSlug(string $slug): string
    {
        $slug = mb_strtolower($slug);
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * Транслитерирует русский текст в латиницу для slug.
     */
    private function transliterate(string $text): string
    {
        $translitMap = [
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'ё' => 'yo',
            'ж' => 'zh',
            'з' => 'z',
            'и' => 'i',
            'й' => 'y',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'х' => 'kh',
            'ц' => 'ts',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'sch',
            'ъ' => '',
            'ы' => 'y',
            'ь' => '',
            'э' => 'e',
            'ю' => 'yu',
            'я' => 'ya',
            'А' => 'A',
            'Б' => 'B',
            'В' => 'V',
            'Г' => 'G',
            'Д' => 'D',
            'Е' => 'E',
            'Ё' => 'Yo',
            'Ж' => 'Zh',
            'З' => 'Z',
            'И' => 'I',
            'Й' => 'Y',
            'К' => 'K',
            'Л' => 'L',
            'М' => 'M',
            'Н' => 'N',
            'О' => 'O',
            'П' => 'P',
            'Р' => 'R',
            'С' => 'S',
            'Т' => 'T',
            'У' => 'U',
            'Ф' => 'F',
            'Х' => 'Kh',
            'Ц' => 'Ts',
            'Ч' => 'Ch',
            'Ш' => 'Sh',
            'Щ' => 'Sch',
            'Ъ' => '',
            'Ы' => 'Y',
            'Ь' => '',
            'Э' => 'E',
            'Ю' => 'Yu',
            'Я' => 'Ya',
            ' ' => '-',
            '_' => '-',
            '.' => '-'
        ];

        $text = mb_strtolower($text);
        $text = strtr($text, $translitMap);

        // Убираем всё кроме латиницы, цифр и дефисов
        $text = preg_replace('/[^a-z0-9\-]/', '', $text);

        // Заменяем множественные дефисы на один
        $text = preg_replace('/-+/', '-', $text);

        // Убираем дефисы в начале и конце
        $text = trim($text, '-');

        // Если slug пустой после транслитерации - используем timestamp
        if (empty($text)) {
            $text = 'page-' . time();
        }

        // Ограничиваем длину
        if (strlen($text) > 200) {
            $text = substr($text, 0, 200);
            $text = rtrim($text, '-');
        }

        return $text;
    }

    /**
     * Получить страницу по slug с увеличением счетчика просмотров.
     */
    public function getPageBySlug(string $slug, ?int $tagId = null): ?array
    {
        $page = $this->wikiPage->getBySlug($slug, $tagId);

        if ($page) {
            $this->wikiPage->incrementViewCount($page['id']);
        }

        return $page;
    }

    /**
     * Получить wiki страницы для тега.
     */
    public function getPagesForTag(int $tagId): array
    {
        return $this->wikiPage->getForTag($tagId);
    }

    /**
     * Получить основную страницу тега.
     */
    public function getPrimaryPageForTag(int $tagId): ?array
    {
        return $this->wikiPage->getPrimaryForTag($tagId);
    }

    /**
     * Поиск по wiki страницам тега.
     */
    public function searchInTag(int $tagId, string $query): array
    {
        return $this->wikiPage->searchInTag($tagId, $query);
    }

    /**
     * Получить страницу по ID.
     */
    public function getById(int $pageId): ?array
    {
        return $this->wikiPage->find($pageId);
    }

    /**
     * Получить историю изменений страницы.
     */
    public function getRevisions(int $pageId): array
    {
        return $this->wikiRevision->getForPage($pageId);
    }

    /**
     * Удаляет (скрывает) wiki страницу.
     */
    public function deletePage(int $pageId, int $userId): bool
    {
        $page = $this->wikiPage->find($pageId);
        if (!$page) {
            Session::setFlash('error', 'Wiki страница не найдена');
            return false;
        }

        $this->wikiPage->delete($pageId);
        Session::setFlash('success', 'Wiki страница успешно удалена');

        // Логирование
        \App\Core\Audit::log('wiki.deleted', 'Пользователь удалил wiki страницу', 'wiki', [
            'page_id' => $pageId,
            'user_id' => $userId
        ]);

        // Диспатч события
        if ($this->eventDispatcher) {
            $this->eventDispatcher->dispatch(new WikiPageDeleted($pageId, $userId));
        }

        return true;
    }
	
	/**
	 * Восстановить удалённую wiki страницу
	 * 
	 * @param int $pageId ID страницы
	 * @param int $userId ID пользователя, восстанавливающего страницу
	 * @return bool true, если восстановление успешно
	 */
	public function restorePage(int $pageId, int $userId): bool
	{
		if ($pageId <= 0 || $userId <= 0) {
			return false;
		}
		
		try {
			// Находим страницу включая удалённые
			$page = $this->wikiPage->findWithDeleted($pageId);
			
			if (!$page) {
				return false;
			}
			
			// Проверяем, что страница действительно удалена
			if (empty($page['deleted_at'])) {
				return false;
			}
			
			// Восстанавливаем
			$result = $this->wikiPage->restore($pageId);
			
			if ($result) {
				Audit::log('wiki.restored', 'Восстановлена wiki страница', 'wiki', [
					'page_id' => $pageId,
					'user_id' => $userId,
					'title' => $page['title'],
				]);
				
				return true;
			}
			
			return false;
			
		} catch (\Throwable $e) {
			error_log("[WIKI] Error restoring page {$pageId}: " . $e->getMessage());
			return false;
		}
	}
}
