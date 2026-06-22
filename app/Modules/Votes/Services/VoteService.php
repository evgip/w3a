<?php

declare(strict_types=1);

namespace App\Modules\Votes\Services;

use App\Modules\Votes\Models\Vote;
use App\Modules\Users\Models\User;
use App\Modules\Stories\Models\Comment;
use App\Core\Logger;

/**
 * Сервис голосования.
 * Отвечает за бизнес-правила (карма, права, запрет самоголосования).
 */
class VoteService
{
    private Vote $voteModel;
    private User $userModel;
    private Comment $commentModel;
    private const DEFAULT_MIN_KARMA = 10;

    public function __construct(Vote $voteModel, User $userModel, Comment $commentModel)
    {
        $this->voteModel = $voteModel;
        $this->userModel = $userModel;
        $this->commentModel = $commentModel;
    }

    /**
     * Обработать голосование.
     */
    public function handleVote(int $userId, string $type, int $targetId, int $voteValue): array
    {
        // ✅ НОВОЕ: Проверка самоголосования
        $ownerCheck = $this->checkSelfVote($userId, $type, $targetId);
        if (!$ownerCheck['allowed']) {
            return $ownerCheck;
        }

        // Проверка кармы для дизлайка
        if ($voteValue === -1 && !$this->canDownvote($userId)) {
            $minKarma = $this->getMinKarma();
            $userKarma = $this->userModel->getUserKarma($userId);
            return [
                'success' => false,
                'message' => "Дизлайки доступны от {$minKarma} кармы. У вас: {$userKarma}.",
            ];
        }

        // Выполняем голосование
        if (!$this->voteModel->toggleVote($userId, $type, $targetId, $voteValue)) {
            return [
                'success' => false,
                'message' => 'Ошибка обработки голоса.',
            ];
        }

        // ✅ НОВОЕ: Обновляем confidence_score для комментариев
        if ($type === 'comment') {
            $this->updateCommentConfidenceScore($targetId);
        }

        return ['success' => true, 'message' => 'Голос учтён.'];
    }

    public function getNewScore(string $type, int $targetId): int
    {
        return $this->voteModel->getScoreForEntity($type, $targetId);
    }

    public function getUserVote(int $userId, string $type, int $targetId): ?int
    {
        return $this->voteModel->getUserVote($userId, $type, $targetId);
    }

    /**
     * ✅ НОВОЕ: Обновить confidence_score комментария после голосования
     */
    private function updateCommentConfidenceScore(int $commentId): void
    {
        try {
            $comment = $this->commentModel->getCommentById($commentId);
            
            if ($comment) {
                $confidenceScore = wilson_score(
                    (int)$comment['score'],
                    (int)$comment['flag_count']
                );
                
                $this->commentModel->updateConfidenceScore($commentId, $confidenceScore);
            }
        } catch (\Exception $e) {
            // Логируем ошибку, но не прерываем процесс голосования
            Logger::error('Failed to update confidence score for comment', [
                'comment_id' => $commentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * ✅ НОВОЕ: Проверка самоголосования.
     * Пользователь не может голосовать за свой контент.
     * 
     * @return array ['allowed' => bool, 'success' => bool, 'message' => string]
     */
    private function checkSelfVote(int $userId, string $type, int $targetId): array
    {
        $ownerId = $this->voteModel->getOwnerUserId($type, $targetId);
        
        // Контент не найден
        if ($ownerId === null) {
            return [
                'allowed' => false,
                'success' => false,
                'message' => 'Контент не найден.',
            ];
        }
        
        // Пользователь пытается голосовать за свой контент
        if ($ownerId === $userId) {
            return [
                'allowed' => false,
                'success' => false,
                'message' => 'Вы не можете голосовать за свой собственный контент.',
            ];
        }
        
        return ['allowed' => true, 'success' => true, 'message' => ''];
    }

    /**
     * Проверить право на дизлайк.
     */
    private function canDownvote(int $userId): bool
    {
        $user = $this->userModel->find($userId);
        
        if (empty($user)) {
            return false;
        }

        if ($this->isUserAdmin($user)) {
            return true;
        }

        return $this->userModel->getUserKarma($userId) >= $this->getMinKarma();
    }

    private function isUserAdmin(array $user): bool
    {
        return (isset($user['role']) && $user['role'] === 'admin')
            || (isset($user['is_admin']) && (int)$user['is_admin'] === 1);
    }

    private function getMinKarma(): int
    {
        return (int)(config('app.min_karma_for_downvote') ?? self::DEFAULT_MIN_KARMA);
    }
}