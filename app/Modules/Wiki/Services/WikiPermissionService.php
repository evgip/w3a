<?php

declare(strict_types=1);

namespace App\Modules\Wiki\Services;

use App\Modules\Wiki\Models\WikiPermission;
use App\Modules\Tags\Models\Tag;
use App\Modules\Users\Models\User;
use App\Core\Session;
use App\Core\Audit;

/**
 * Сервис для управления правами доступа к wiki.
 *
 * Отвечает за проверку и выдачу прав пользователям на wiki для тегов.
 * 
 * ✅ ИЗМЕНЕНО: Все зависимости обязательны и внедряются через конструктор.
 */
class WikiPermissionService
{
    private WikiPermission $wikiPermission;
    private Tag $tag;
    private User $userModel;
    private Session $session;
    private Audit $audit;

    /**
     * ✅ ИЗМЕНЕНО: Все зависимости обязательны
     */
    public function __construct(
        WikiPermission $wikiPermission,
        Tag $tag,
        User $userModel,
        Session $session,
        Audit $audit
    ) {
        $this->wikiPermission = $wikiPermission;
        $this->tag = $tag;
        $this->userModel = $userModel;
        $this->session = $session;
        $this->audit = $audit;
    }

    /**
     * Проверить может ли пользователь создавать wiki для тега.
     */
    public function canCreateWikiForTag(int $tagId, int $userId): bool
    {
        // Админы и модераторы всегда могут
        if (\App\Modules\Auth\Services\Auth::isAdmin() || \App\Modules\Auth\Services\Auth::isModerator()) {
            return true;
        }

        // Автор тега может
        $tag = $this->tag->find($tagId);
        if ($tag && isset($tag['user_id']) && (int)$tag['user_id'] === $userId) {
            return true;
        }

        // Проверить явные права
        $permission = $this->wikiPermission->getUserPermission($tagId, $userId);
        return $permission && $permission['can_edit'];
    }

    /**
     * Проверить может ли пользователь редактировать страницу.
     */
    public function canEditPage(array $page, int $userId): bool
    {
        // Админы и модераторы всегда могут
        if (\App\Modules\Auth\Services\Auth::isAdmin() || \App\Modules\Auth\Services\Auth::isModerator()) {
            return true;
        }

        // Автор страницы может
        if ((int)$page['author_id'] === $userId) {
            return true;
        }

        // Если страница привязана к тегу - проверить права для тега
        if (!empty($page['tag_id'])) {
            return $this->canCreateWikiForTag((int)$page['tag_id'], $userId);
        }

        return false;
    }

    /**
     * Проверить может ли пользователь удалять страницу.
     */
    public function canDeletePage(array $page, int $userId): bool
    {
        // Админы всегда могут
        if (\App\Modules\Auth\Services\Auth::isAdmin()) {
            return true;
        }

        // Модераторы могут
        if (\App\Modules\Auth\Services\Auth::isModerator()) {
            return true;
        }

        // Если страница привязана к тегу - проверить права
        if (!empty($page['tag_id'])) {
            $tag = $this->tag->find((int)$page['tag_id']);

            // Автор тега может
            if ($tag && isset($tag['user_id']) && (int)$tag['user_id'] === $userId) {
                return true;
            }

            // Проверить явные права на удаление
            $permission = $this->wikiPermission->getUserPermission((int)$page['tag_id'], $userId);
            return $permission && $permission['can_delete'];
        }

        // Автор страницы может удалить свою страницу
        return (int)$page['author_id'] === $userId;
    }

    /**
     * Дать права пользователю.
     */
    public function grantPermission(
        int $tagId, 
        string $username, 
        int $grantedBy, 
        bool $canEdit = true, 
        bool $canDelete = false
    ): bool {
        // Проверить что дающий права имеет право это делать
        $tag = $this->tag->find($tagId);
        if (!$tag || !isset($tag['user_id']) || (int)$tag['user_id'] !== $grantedBy) {
            if (!\App\Modules\Auth\Services\Auth::isAdmin()) {
                $this->session->flash('error', 'Только автор тега может давать права');
                return false;
            }
        }

        // ✅ Используем внедрённую модель User
        $targetUser = $this->userModel->findByName($username);

        if (!$targetUser) {
            $this->session->flash('error', 'Пользователь не найден');
            return false;
        }

        $targetUserId = (int)$targetUser['id'];

        // Проверить существование
        $existing = $this->wikiPermission->getUserPermission($tagId, $targetUserId);

        if ($existing) {
            // Обновить
            $result = $this->wikiPermission->update((int)$existing['id'], [
                'can_edit' => $canEdit ? 1 : 0,
                'can_delete' => $canDelete ? 1 : 0
            ]);
        } else {
            // Создать
            $result = (bool)$this->wikiPermission->create([
                'tag_id' => $tagId,
                'user_id' => $targetUserId,
                'can_edit' => $canEdit ? 1 : 0,
                'can_delete' => $canDelete ? 1 : 0,
                'granted_by' => $grantedBy
            ]);
        }

        // ✅ Используем внедрённый Audit
        $this->audit->log('wiki.permission_granted', "Выданы права на wiki для тега ID: {$tagId}", 'wiki', [
            'tag_id' => $tagId,
            'user_id' => $targetUserId,
            'granted_by' => $grantedBy,
            'can_edit' => $canEdit,
            'can_delete' => $canDelete
        ]);

        return $result;
    }

    /**
     * Отозвать права пользователя.
     */
    public function revokePermission(int $tagId, int $targetUserId, int $revokedBy): bool
    {
        // Проверить что отзывающий имеет право это делать
        $tag = $this->tag->find($tagId);
        if (!$tag || !isset($tag['user_id']) || (int)$tag['user_id'] !== $revokedBy) {
            if (!\App\Modules\Auth\Services\Auth::isAdmin()) {
                $this->session->flash('error', 'Только автор тега может отзывать права');
                return false;
            }
        }

        $permission = $this->wikiPermission->getUserPermission($tagId, $targetUserId);
        if (!$permission) {
            $this->session->flash('error', 'Права не найдены');
            return false;
        }

        $result = $this->wikiPermission->delete((int)$permission['id']);

        // ✅ Используем внедрённый Audit
        $this->audit->log('wiki.permission_revoked', "Отозваны права на wiki для тега ID: {$tagId}", 'wiki', [
            'tag_id' => $tagId,
            'user_id' => $targetUserId,
            'revoked_by' => $revokedBy
        ]);

        return $result;
    }

    /**
     * Получить всех редакторов тега.
     */
    public function getTagEditors(int $tagId): array
    {
        return $this->wikiPermission->getTagEditors($tagId);
    }
}