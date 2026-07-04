<?php

declare(strict_types=1);

namespace App\Modules\Votes\Controllers;

use App\Core\Controller;
use App\Core\Logger;
use App\Modules\Votes\Services\VoteService;
use App\Modules\Auth\Services\Auth;

/**
 * Контроллер голосования.
 * Маршрут защищён middleware: web + auth.
 * 
 * ✅ ИЗМЕНЕНО: VoteService получается из контейнера
 */
class VotesController extends Controller
{
    private const ALLOWED_TYPES = ['story', 'comment'];
    private const ALLOWED_DIRECTIONS = ['up', 'down'];

    /**
     * ✅ Хелпер: получить VoteService из контейнера
     */
    private function voteService(): VoteService
    {
        return $this->service(VoteService::class);
    }

    /**
     * ✅ Хелпер: получить Logger из контейнера
     */
    private function logger(): Logger
    {
        return $this->container->get(Logger::class);
    }

    public function handle(string $type, string $id, string $direction): void
    {
        // 1. Быстрая валидация
        if (!$this->validateInput($type, $id, $direction)) {
            return;
        }

        // ✅ Используем Auth::id() вместо $_SESSION
        $userId = (int) Auth::id();
        $targetId = (int)$id;
        $voteValue = ($direction === 'down') ? -1 : 1;

        // 2. Обработка голоса
        try {
            $result = $this->voteService()->handleVote($userId, $type, $targetId, $voteValue);
        } catch (\Throwable $e) {
            // ✅ Используем внедрённый Logger
            $this->logger()->error('Vote failed', [
                'user_id' => $userId,
                'type' => $type,
                'target_id' => $targetId,
                'error' => $e->getMessage(),
            ]);
            $this->jsonError('Внутренняя ошибка сервера.', 500);
            return;
        }

        if (!$result['success']) {
            $this->jsonError($result['message'], 403);
            return;
        }

        // 3. Возвращаем актуальные данные
        $this->jsonSuccess([
            'new_score'  => $this->voteService()->getNewScore($type, $targetId),
            'vote_state' => $this->voteService()->getUserVote($userId, $type, $targetId),
        ]);
    }

    private function validateInput(string $type, string $id, string $direction): bool
    {
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            $this->jsonError('Недопустимый тип сущности.', 400);
            return false;
        }
        if (!ctype_digit($id) || (int)$id <= 0) {
            $this->jsonError('Недопустимый ID.', 400);
            return false;
        }
        if (!in_array($direction, self::ALLOWED_DIRECTIONS, true)) {
            $this->jsonError('Недопустимое направление.', 400);
            return false;
        }
        return true;
    }

    private function jsonSuccess(array $data): void
    {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'success'] + $data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function jsonError(string $message, int $code = 400): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}