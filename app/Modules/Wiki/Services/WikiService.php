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
    private Session $session;
    private Audit $audit;
    private ?EventDispatcher $eventDispatcher;

    public function __construct(
        WikiPage $wikiPage,
        WikiRevision $wikiRevision,
        Session $session,
        Audit $audit,
        ?EventDispatcher $eventDispatcher = null
    ) {
        $this->wikiPage = $wikiPage;
        $this->wikiRevision = $wikiRevision;
        $this->session = $session;
        $this->audit = $audit;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Создаёт новую wiki страницу с валидацией.
     */
    public function createPage(array $data, int $userId): int
    {
        if (empty($data['title'])) {
            $this->session->flash('error', 'Заголовок не может быть пустым');
            return 0;
        }

        if (empty($data['content'])) {
            $this->session->flash('error', 'Содержимое не может быть пустым');
            return 0;
        }

        if (mb_strlen($data['title']) < 3) {
            $this->session->flash('error', 'Заголовок слишком короткий (минимум 3 символа)');
            return 0;
        }

        $slug = $this->prepareSlug($data['slug'] ?? '', $data['title']);

        if (!$slug) {
            return 0;
        }

        if ($this->wikiPage->slugExists($slug, (int)$data['tag_id'])) {
            $this->session->flash('error', 'Страница с таким URL уже существует. Попробуйте другой slug.');
            return 0;
        }

        $renderedContent = markdown($data['content']);

        if (!empty($data['is_primary']) && isset($data['tag_id'])) {
            $this->wikiPage->resetPrimaryFlag($data['tag_id']);
        }

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
            $this->wikiRevision->create([
                'wiki_page_id' => $pageId,
                'revision_number' => 1,
                'content' => $data['content'],
                'edit_summary' => 'Первоначальное создание',
                'user_id' => $userId
            ]);

            // Используем внедрённый Audit
            $this->audit->log('wiki.created', 'Пользователь создал wiki страницу', 'wiki', [
                'page_id' => $pageId,
                'tag_id' => $data['tag_id'] ?? null,
                'user_id' => $userId
            ]);

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
            $this->session->flash('error', 'Wiki страница не найдена');
            return false;
        }

        if (empty($data['title'])) {
            $this->session->flash('error', 'Заголовок не может быть пустым');
            return false;
        }

        if (empty($data['content'])) {
            $this->session->flash('error', 'Содержимое не может быть пустым');
            return false;
        }

        if (mb_strlen($data['title']) < 3) {
            $this->session->flash('error', 'Заголовок слишком короткий (минимум 3 символа)');
            return false;
        }

        $slug = $this->prepareSlug($data['slug'] ?? '', $data['title']);

        if (!$slug) {
            return false;
        }

        $currentPage = $this->wikiPage->find($pageId);
        if (!$currentPage) {
            return false;
        }
        
        if (isset($data['slug']) && $data['slug'] !== $currentPage['slug']) {
            if ($this->wikiPage->slugExists($data['slug'], (int)$currentPage['tag_id'], $pageId)) {
                $this->session->flash('error', 'Страница с таким URL уже существует в этом теге.');
                return false;
            }
        }

        $renderedContent = markdown($data['content']);

        if (!empty($data['is_primary']) && $page['tag_id']) {
            $this->wikiPage->resetPrimaryFlag($page['tag_id']);
        }

        $this->wikiPage->update($pageId, [
            'title' => $data['title'],
            'slug' => $slug,
            'content' => $data['content'],
            'rendered_content' => $renderedContent,
            'is_primary' => $data['is_primary'] ?? 0,
            'status' => $data['status'] ?? 'published'
        ]);

        $revisionNumber = $this->wikiRevision->getNextRevisionNumber($pageId);
        $this->wikiRevision->create([
            'wiki_page_id' => $pageId,
            'revision_number' => $revisionNumber,
            'content' => $data['content'],
            'edit_summary' => $data['edit_summary'] ?? '',
            'user_id' => $userId
        ]);

        $this->audit->log('wiki.updated', 'Пользователь обновил wiki страницу', 'wiki', [
            'page_id' => $pageId,
            'user_id' => $userId,
            'revision' => $revisionNumber
        ]);

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

    private function prepareSlug(string $userSlug, string $title): ?string
    {
        if (!empty($userSlug)) {
            $slug = $this->sanitizeSlug($userSlug);

            if (empty($slug)) {
                $this->session->flash('error', 'URL может содержать только латинские буквы, цифры и дефисы');
                return null;
            }

            if (strlen($slug) < 3) {
                $this->session->flash('error', 'URL слишком короткий (минимум 3 символа)');
                return null;
            }

            if (strlen($slug) > 200) {
                $this->session->flash('error', 'URL слишком длинный (максимум 200 символов)');
                return null;
            }

            return $slug;
        }

        return $this->transliterate($title);
    }

    private function sanitizeSlug(string $slug): string
    {
        $slug = mb_strtolower($slug);
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    private function transliterate(string $text): string
    {
        $translitMap = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
            'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
            'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
            'У' => 'U', 'Ф' => 'F', 'Х' => 'Kh', 'Ц' => 'Ts', 'Ч' => 'Ch',
            'Ш' => 'Sh', 'Щ' => 'Sch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '',
            'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
            ' ' => '-', '_' => '-', '.' => '-'
        ];

        $text = mb_strtolower($text);
        $text = strtr($text, $translitMap);
        $text = preg_replace('/[^a-z0-9\-]/', '', $text);
        $text = preg_replace('/-+/', '-', $text);
        $text = trim($text, '-');

        if (empty($text)) {
            $text = 'page-' . time();
        }

        if (strlen($text) > 200) {
            $text = substr($text, 0, 200);
            $text = rtrim($text, '-');
        }

        return $text;
    }

    public function getPageBySlug(string $slug, ?int $tagId = null): ?array
    {
        $page = $this->wikiPage->getBySlug($slug, $tagId);

        if ($page) {
            $this->wikiPage->incrementViewCount($page['id']);
        }

        return $page;
    }

    public function getPagesForTag(int $tagId): array
    {
        return $this->wikiPage->getForTag($tagId);
    }

    public function getPrimaryPageForTag(int $tagId): ?array
    {
        return $this->wikiPage->getPrimaryForTag($tagId);
    }

    public function searchInTag(int $tagId, string $query): array
    {
        return $this->wikiPage->searchInTag($tagId, $query);
    }

    public function getById(int $pageId): ?array
    {
        return $this->wikiPage->find($pageId);
    }

    public function getRevisions(int $pageId): array
    {
        return $this->wikiRevision->getForPage($pageId);
    }

    public function deletePage(int $pageId, int $userId): bool
    {
        $page = $this->wikiPage->find($pageId);
        if (!$page) {
            $this->session->flash('error', 'Wiki страница не найдена');
            return false;
        }

        $this->wikiPage->delete($pageId);
        $this->session->flash('success', 'Wiki страница успешно удалена');

        $this->audit->log('wiki.deleted', 'Пользователь удалил wiki страницу', 'wiki', [
            'page_id' => $pageId,
            'user_id' => $userId
        ]);

        if ($this->eventDispatcher) {
            $this->eventDispatcher->dispatch(new WikiPageDeleted($pageId, $userId));
        }

        return true;
    }
    
    public function restorePage(int $pageId, int $userId): bool
    {
        if ($pageId <= 0 || $userId <= 0) {
            return false;
        }
        
        try {
            $page = $this->wikiPage->findWithDeleted($pageId);
            
            if (!$page) {
                return false;
            }
            
            if (empty($page['deleted_at'])) {
                return false;
            }
            
            $result = $this->wikiPage->restore($pageId);
            
            if ($result) {
                // ✅ Используем внедрённый Audit
                $this->audit->log('wiki.restored', 'Восстановлена wiki страница', 'wiki', [
                    'page_id' => $pageId,
                    'user_id' => $userId,
                    'title' => $page['title'],
                ]);
                
                return true;
            }
            
            return false;
            
        } catch (\Throwable $e) {
            // ✅ Используем error_log вместо статического Logger
            error_log("[WIKI] Error restoring page {$pageId}: " . $e->getMessage());
            return false;
        }
    }
}