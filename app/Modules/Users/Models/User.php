<?php

namespace App\Modules\Users\Models;

use App\Core\Model;
use App\Core\Database;

class User extends Model
{
    protected string $table = 'users';

    /**
     * Получить полную статистику активности пользователя по его ID
     * 
     * @param int $userId
     * @return array Массив с ключами 'stories_count' и 'comments_count'
     */
    public function getProfileStats(int $userId): array
    {
        $db = Database::getConnection();

        // 1. Считаем активные истории автора
        $storiesStmt = $db->prepare("SELECT COUNT(*) FROM `stories` WHERE `user_id` = :uid AND `deleted_at` IS NULL");
        $storiesStmt->execute(['uid' => $userId]);
        $storiesCount = (int)$storiesStmt->fetchColumn();

        // 2. Считаем активные комментарии автора
        $commentsStmt = $db->prepare("SELECT COUNT(*) FROM `comments` WHERE `user_id` = :uid AND `deleted_at` IS NULL");
        $commentsStmt->execute(['uid' => $userId]);
        $commentsCount = (int)$commentsStmt->fetchColumn();

        return [
            'stories_count'  => $storiesCount,
            'comments_count' => $commentsCount
        ];
    }
	
     /**
     * Вычислить суммарную карму пользователя (рейтинг всех его постов + комментов)
     */
    public function getUserKarma(int $userId): int
    {
        $db = \App\Core\Database::getConnection();

        // 1. Считаем сумму score всех активных историй автора
        $storyStmt = $db->prepare("SELECT SUM(`score`) FROM `stories` WHERE `user_id` = :uid AND `deleted_at` IS NULL");
        $storyStmt->execute(['uid' => $userId]);
        $storyKarma = (int)$storyStmt->fetchColumn();

        // 2. Считаем сумму score всех активных комментариев автора
        $commentStmt = $db->prepare("SELECT SUM(`score`) FROM `comments` WHERE `user_id` = :uid AND `deleted_at` IS NULL");
        $commentStmt->execute(['uid' => $userId]);
        $commentKarma = (int)$commentStmt->fetchColumn();

        // Итоговая карма — это сумма двух показателей
        return $storyKarma + $commentKarma;
    }
}
