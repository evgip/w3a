<?php

namespace App\Modules\Votes\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Auth;
use App\Core\Session;
use App\Modules\Votes\Models\Vote;

class VotesController extends Controller
{
    /**
     * Processing center for global platform rating votes actions (POST /vote/{type}/{id}/{direction})
     * Асинхронная обработка полиморфного голосования (POST /vote/{type}/{id}/{direction})
     */
    public function handle(string $type, string $id, string $direction): void
    {
        // Всегда отдаем правильный JSON-заголовок
        header('Content-Type: application/json');

        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Необходима авторизация.']);
            exit;
        }

        $request = new Request();
        // ВНИМАНИЕ: Для AJAX-запросов токен CSRF должен передаваться в HTTP-заголовках или POST-поле
        $request->validateCsrf();

        if ($type !== 'story' && $type !== 'comment') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Неверный тип сущности.']);
            exit;
        }

        $userId    = (int)$_SESSION['user_id'];
        $voteValue = ($direction === 'down') ? -1 : 1;
        $targetId  = (int)$id;

        // --- ЛОБСТЕР-ЗАЩИТА КАРМЫ ДЛЯ ДИЗЛАЙКОВ ---
        if ($direction === 'down') {
            $minKarma = config_int('config.app.min_karma_for_downvote', 10);

            $userModel = new \App\Modules\Users\Models\User();
            $userKarma = $userModel->getUserKarma($userId);

            if ($userKarma < $minKarma) {
                http_response_code(403);
                echo json_encode([
                    'status' => 'error', 
                    'message' => "Дизлайки доступны от {$minKarma} баллов кармы. У вас: {$userKarma}."
                ]);
                exit;
            }
        }

        $voteModel = new Vote();
        $voteModel->toggleVote($userId, $type, $targetId, $voteValue);

        // Получаем свежий пересчитанный score из таблицы сущности для отправки на фронтенд
        $db = \App\Core\Database::getConnection();
        $targetTable = ($type === 'story') ? 'stories' : 'comments';
        $stmt = $db->prepare("SELECT `score` FROM `{$targetTable}` WHERE `id` = :id LIMIT 1");
        $stmt->execute(['id' => $targetId]);
        $newScore = (int)$stmt->fetchColumn();

        // Узнаем новый статус стрелочки (чтобы фронтенд знал, подсвечивать её или гасить)
        $currentVoteState = $voteModel->getUserVote($userId, $type, $targetId);

        \App\Core\Audit::log('vote.ajax_toggled', 'Пользователь изменил оценку через AJAX', ['type' => $type, 'id' => $targetId]);

        // Возвращаем JSON-пакет для мгновенного обновления DOM в браузере
        echo json_encode([
            'status' => 'success',
            'new_score' => $newScore,
            'vote_state' => $currentVoteState // 1, -1 или null
        ]);
        exit;
    }
}
