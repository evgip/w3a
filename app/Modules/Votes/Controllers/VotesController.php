<?php

namespace App\Modules\Votes\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Auth;
use App\Modules\Votes\Models\Vote;
use App\Modules\Votes\Services\VoteService;
use App\Modules\Users\Models\User;

class VotesController extends Controller
{
    private ?VoteService $voteService = null;

    private function getVoteService(): VoteService
    {
        if ($this->voteService === null) {
            $this->voteService = new VoteService(
                new Vote(),
                new User()
            );
        }
        return $this->voteService;
    }

    public function handle(string $type, string $id, string $direction): void
    {
        header('Content-Type: application/json');

        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Необходима авторизация.']);
            exit;
        }

        $request = new Request();
        $request->validateCsrf();

        $userId = (int)$_SESSION['user_id'];
        $voteValue = ($direction === 'down') ? -1 : 1;
        $targetId = (int)$id;

        // Обработка голоса через сервис
        $result = $this->getVoteService()->handleVote($userId, $type, $targetId, $voteValue);

        if (!$result['success']) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => $result['message']
            ]);
            exit;
        }

        // Получаем свежий score
        $newScore = $this->getVoteService()->getNewScore($type, $targetId);
        $currentVoteState = $this->getVoteService()->getUserVote($userId, $type, $targetId);

        \App\Core\Audit::log('vote.ajax_toggled', 'Пользователь изменил оценку через AJAX', ['type' => $type, 'id' => $targetId]);

        echo json_encode([
            'status' => 'success',
            'new_score' => $newScore,
            'vote_state' => $currentVoteState
        ]);

        exit;
    }
}