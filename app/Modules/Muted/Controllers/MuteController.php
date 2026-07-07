<?php
// app/Modules/Muted/Controllers/MuteController.php

declare(strict_types=1);

namespace App\Modules\Muted\Controllers;

use App\Core\Controller;
use App\Modules\Muted\Services\MuteService;
use App\Modules\Auth\Services\Auth;
use App\Modules\Users\Models\User;

class MuteController extends Controller
{
    /**
     * Список замьюченных пользователей
     */
    public function list(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login');
            return;
        }

        $userId = Auth::id();
        $muteService = $this->service(MuteService::class);
        $mutedUsers = $muteService->getMutedList($userId);

        $this->render('list', [
            'title' => 'Замьюченные пользователи',
            'mutedUsers' => $mutedUsers,
            'currentUserId' => $userId,
        ]);
    }

    /**
     * Переключить мьют (AJAX + обычный запрос)
     */
    public function toggle(string $id): void
    {
        if (!Auth::check()) {
            $this->json(['error' => 'Необходима авторизация'], 401);
            return;
        }

        $targetUserId = (int)$id;
        $userId = Auth::id();

        if ($userId === $targetUserId) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->json(['error' => 'Нельзя замьютить самого себя'], 400);
                return;
            }
            $this->session()->flash('error', 'Нельзя замьютить самого себя');
            $this->redirectBack();
            return;
        }

        // Проверяем, что пользователь существует
        $userModel = $this->container->get(User::class);
        $targetUser = $userModel->find($targetUserId);
        if (!$targetUser) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $this->json(['error' => 'Пользователь не найден'], 404);
                return;
            }
            $this->redirectBack();
            return;
        }

        $muteService = $this->service(MuteService::class);
        $isMuted = $muteService->toggle($userId, $targetUserId);

        // Поддержка AJAX
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $this->json([
                'success' => true,
                'is_muted' => $isMuted,
                'username' => $targetUser['username'],
            ]);
            return;
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? '/muted';
        $this->redirectBack($referer);
    }

    private function session(): \App\Core\Session
    {
        return $this->container->get(\App\Core\Session::class);
    }
}