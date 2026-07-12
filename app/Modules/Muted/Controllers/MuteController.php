<?php

declare(strict_types=1);

namespace App\Modules\Muted\Controllers;

use App\Core\Controller;
use App\Modules\Muted\Services\MuteService;
use App\Modules\Users\Models\User;

/**
 * Контроллер управления игнорируемыми пользователями (mute).
 * 
 * Все маршруты защищены middleware ['web', 'auth'],
 * поэтому проверки авторизации в контроллере не требуются.
 */
class MuteController extends Controller
{
    /**
     * Список игнорируемых пользователей (GET /muted).
     * 
     * Показывает всех пользователей, которых текущий пользователь
     * добавил в список игнорируемых.
     */
    public function list(): void
    {
        $userContext = $this->getUserContext();
        $muteService = $this->service(MuteService::class);
        $mutedUsers = $muteService->getMutedList($userContext['id']);

        $this->render('list', [
            'title' => 'Игнорируемые пользователи',
            'mutedUsers' => $mutedUsers,
            'currentUserId' => $userContext['id'],
        ]);
    }

    /**
     * Переключение статуса игнорирования пользователя (POST /mute/toggle/{id}).
     * 
     * Если пользователь уже в списке игнорируемых — удаляет его.
     * Если не в списке — добавляет.
     * 
     * Валидация:
     * - Нельзя игнорировать самого себя
     * - Нельзя игнорировать несуществующего пользователя
     * 
     * Поддерживает два режима ответа:
     * - AJAX: возвращает JSON с новым статусом is_muted
     * - Обычный POST: редирект с flash-сообщением
     */
    public function toggle(string $id): void
    {
        $userContext = $this->getUserContext();
        $targetUserId = (int)$id;
        $isAjax = $this->request->isAjaxRequest();

        // Нельзя игнорировать самого себя
        if ($userContext['id'] === $targetUserId) {
            if ($isAjax) {
                $this->json(['error' => 'Нельзя игнорировать самого себя'], 400);
                return;
            }
            $this->backWithMessage('Нельзя игнорировать самого себя', 'error');
            return;
        }

        // Проверяем, что пользователь существует
        $userModel = $this->container->get(User::class);
        $targetUser = $userModel->find($targetUserId);

        if (!$targetUser) {
            if ($isAjax) {
                $this->json(['error' => 'Пользователь не найден'], 404);
                return;
            }
            $this->redirectBack();
            return;
        }

        // Переключаем статус игнорирования
        $muteService = $this->service(MuteService::class);
        $isMuted = $muteService->toggle($userContext['id'], $targetUserId);

        // Ответ для AJAX-запросов
        if ($isAjax) {
            $this->json([
                'success' => true,
                'is_muted' => $isMuted,
                'username' => $targetUser['username'],
            ]);
            return;
        }

        // Ответ для обычных POST-запросов
        $message = $isMuted
            ? "Пользователь {$targetUser['username']} добавлен в игнор-лист"
            : "Пользователь {$targetUser['username']} удалён из игнор-листа";

        $this->redirectWithMessage('/muted', $message, 'success');
    }
}
