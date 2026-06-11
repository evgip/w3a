<?php

namespace App\Modules\Votes\Models;

use App\Core\Model;
use App\Core\Database;

class Vote extends Model
{
    protected string $table = 'votes';

    /**
     * Проверить, голосовал ли пользователь за конкретный объект
     */
    public function getUserVote(int $userId, string $type, int $id): ?int
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT `vote_type` FROM `votes` WHERE `user_id` = :uid AND `votable_type` = :type AND `votable_id` = :id LIMIT 1");
        $stmt->execute(['uid' => $userId, 'type' => $type, 'id' => $id]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (int)$val : null;
    }



    /**
     * Advanced Polymorphic Voting Toggle Engine (Supports Upvotes and Downvotes)
     * 
     * @param int $userId Active user ID
     * @param string $type Entity type ('story' or 'comment')
     * @param int $id Database ID of the target row
     * @param int $voteValue Intended vote direction (1 or -1)
     */
    public function toggleVote(int $userId, string $type, int $id, int $voteValue): bool
    {
        // Enforce boundary parameters validation checks
        if ($voteValue !== 1 && $voteValue !== -1) {
            return false;
        }

        $targetTable = ($type === 'story') ? 'stories' : 'comments';
        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            $existingVote = $this->getUserVote($userId, $type, $id);
            $scoreDelta = 0;

            if ($existingVote === $voteValue) {
                // CASE 1: Clicking the same active arrow again retracts the vote completely
                $stmt = $db->prepare("DELETE FROM `votes` WHERE `user_id` = :uid AND `votable_type` = :type AND `votable_id` = :id");
                $stmt->execute(['uid' => $userId, 'type' => $type, 'id' => $id]);
                
                // Subtract whatever value they originally added (Upvote -> -1, Downvote -> +1)
                $scoreDelta = -$voteValue;
            } else {
                if ($existingVote !== null) {
                    // CASE 2: Switching vote choices (e.g., from Upvote to Downvote)
                    // Update the active tracking reference value flag inline
                    $stmt = $db->prepare("UPDATE `votes` SET `vote_type` = :vval WHERE `user_id` = :uid AND `votable_type` = :type AND `votable_id` = :id");
                    $stmt->execute(['vval' => $voteValue, 'uid' => $userId, 'type' => $type, 'id' => $id]);
                    
                    // Shifting a direction applies a double mutation weight (e.g., from +1 to -1 requires a -2 delta)
                    $scoreDelta = $voteValue * 2;
                } else {
                    // CASE 3: Brand new vote allocation record insertion
                    $stmt = $db->prepare("INSERT INTO `votes` (`user_id`, `votable_type`, `votable_id`, `vote_type`) VALUES (:uid, :type, :id, :vval)");
                    $stmt->execute(['uid' => $userId, 'type' => $type, 'id' => $id, 'vval' => $voteValue]);
                    
                    $scoreDelta = $voteValue;
                }
            }

            // Execute atomic score adjustment mutations down to the target entity row column
            if ($scoreDelta !== 0) {
                $stmt = $db->prepare("UPDATE `{$targetTable}` SET `score` = `score` + (:delta) WHERE `id` = :id");
                $stmt->bindValue(':delta', $scoreDelta, \PDO::PARAM_INT);
                $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
                $stmt->execute();
            }

            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            \App\Core\Logger::error("Polymorphic voting engine transaction drop: " . $e->getMessage());
            return false;
        }
    }
}
