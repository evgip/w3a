<?php

namespace App\Modules\Votes\Services;

use App\Modules\Votes\Models\Vote;
use App\Modules\Users\Models\User;

/**
 * Сервис для управления голосованием за контент.
 * Инкапсулирует бизнес-логику проверки кармы и прав доступа.
 */
class VoteService
{
    private Vote $voteModel;
    private User $userModel;

    public function __construct(Vote $voteModel, User $userModel)
    {
        $this->voteModel = $voteModel;
        $this->userModel = $userModel;
    }

    /**
     * Обработать голосование с проверкой прав.
     * 
     * @return array ['success' => bool, 'message' => string]
     */
    public function handleVote(int $userId, string $type, int $targetId, int $voteValue): array
    {
        // Валидация типа сущности
        if ($type !== 'story' && $type !== 'comment') {
            return [
                'success' => false,
                'message' => 'Неверный тип сущности.'
            ];
        }

        // Проверка кармы для дизлайка
        if ($voteValue === -1) {
            $minKarma = config_int('config.app.min_karma_for_downvote', 10);
            $user = $this->userModel->find($userId);
            
            // Админы голосуют без ограничений
            $isAdmin = $this->isAdmin($user);
            
            if (!$isAdmin) {
                $userKarma = $this->userModel->getUserKarma($userId);
                if ($userKarma < $minKarma) {
                    return [
                        'success' => false,
                        'message' => "Дизлайки доступны от {$minKarma} баллов кармы. У вас: {$userKarma}."
                    ];
                }
            }
        }

        // Выполняем голосование через модель
        $result = $this->voteModel->toggleVote($userId, $type, $targetId, $voteValue);

        if (!$result) {
            return [
                'success' => false,
                'message' => 'Ошибка обработки голоса.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Голос учтён.'
        ];
    }

    /**
     * Получить новый score из целевой таблицы.
     */
    public function getNewScore(string $type, int $targetId): int
    {
        $db = \App\Core\Database::getConnection();
        $targetTable = ($type === 'story') ? 'stories' : 'comments';
        $stmt = $db->prepare("SELECT `score` FROM `{$targetTable}` WHERE `id` = :id LIMIT 1");
        $stmt->execute(['id' => $targetId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Получить текущий голос пользователя.
     */
    public function getUserVote(int $userId, string $type, int $targetId): ?int
    {
        return $this->voteModel->getUserVote($userId, $type, $targetId);
    }

    /**
     * Проверить, является ли пользователь администратором.
     */
    private function isAdmin(?array $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (isset($user['role']) && $user['role'] === 'admin') {
            return true;
        }

        if (isset($user['is_admin']) && (int)$user['is_admin'] === 1) {
            return true;
        }

        return false;
    }
}