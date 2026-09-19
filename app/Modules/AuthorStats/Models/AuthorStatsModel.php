<?php

declare(strict_types=1);

namespace App\Modules\AuthorStats\Models;

use W3a\Core\Database\Database;

class AuthorStatsModel
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getTotalViews(int $userId): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `story_views` sv
             JOIN `stories` s ON s.id = sv.story_id
             WHERE s.user_id = :uid AND s.deleted_at IS NULL",
            ['uid' => $userId]
        );
    }

    public function getUniqueReaders(int $userId): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(DISTINCT sv.user_id) FROM `story_views` sv
             JOIN `stories` s ON s.id = sv.story_id
             WHERE s.user_id = :uid AND s.deleted_at IS NULL",
            ['uid' => $userId]
        );
    }

    public function getAvgReadTime(int $userId): float
    {
        return (float)$this->db->fetchColumn(
            "SELECT AVG(sv.read_seconds) FROM `story_views` sv
             JOIN `stories` s ON s.id = sv.story_id
             WHERE s.user_id = :uid AND s.deleted_at IS NULL",
            ['uid' => $userId]
        );
    }

    /**
     * Медиана времени чтения (в секундах).
     * Медиана устойчива к выбросам, в отличие от среднего.
     */
    public function getMedianReadTime(int $userId): float
    {
        $sql = "SELECT sv.read_seconds FROM `story_views` sv
                JOIN `stories` s ON s.id = sv.story_id
                WHERE s.user_id = :uid AND s.deleted_at IS NULL AND sv.read_seconds > 0
                ORDER BY sv.read_seconds ASC";

        $values = $this->db->fetchAll($sql, ['uid' => $userId]) ?: [];
        $values = array_column($values, 'read_seconds');
        $count = count($values);

        if ($count === 0) {
            return 0.0;
        }

        $middle = intdiv($count, 2);
        if ($count % 2 === 1) {
            return (float)$values[$middle];
        }

        return ((float)$values[$middle - 1] + (float)$values[$middle]) / 2;
    }

    public function getTotalClapsReceived(int $userId): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(v.claps), 0) FROM `votes` v
             JOIN `stories` s ON s.id = v.votable_id AND v.votable_type = 'story'
             WHERE s.user_id = :uid AND s.deleted_at IS NULL",
            ['uid' => $userId]
        );
    }

    public function getStoriesStats(int $userId): array
    {
        $sql = "
            SELECT
                s.id,
                s.title,
                s.created_at,
                s.score,
                s.reading_time,
                s.comments_count,
                COALESCE(views.cnt, 0) as views,
                COALESCE(views.avg_sec, 0) as avg_seconds,
                COALESCE(claps.cnt, 0) as claps
            FROM `stories` s
            LEFT JOIN (
                SELECT story_id, COUNT(*) as cnt, AVG(read_seconds) as avg_sec
                FROM `story_views`
                GROUP BY story_id
            ) views ON views.story_id = s.id
            LEFT JOIN (
                SELECT votable_id, SUM(claps) as cnt
                FROM `votes`
                WHERE votable_type = 'story'
                GROUP BY votable_id
            ) claps ON claps.votable_id = s.id
            WHERE s.user_id = :uid AND s.deleted_at IS NULL AND s.status = 'published'
            ORDER BY s.created_at DESC
        ";

        return $this->db->fetchAll($sql, ['uid' => $userId]) ?: [];
    }

    public function getRecentReaders(int $userId, int $limit = 20): array
    {
        $sql = "
            SELECT DISTINCT sv.user_id, u.username, up.avatar, MAX(sv.updated_at) as last_read
            FROM `story_views` sv
            JOIN `stories` s ON s.id = sv.story_id
            JOIN `users` u ON u.id = sv.user_id
            LEFT JOIN `user_profiles` up ON up.user_id = u.id
            WHERE s.user_id = :uid AND s.deleted_at IS NULL AND s.status = 'published'
            GROUP BY sv.user_id
            ORDER BY last_read DESC
            LIMIT :lim
        ";

        return $this->db->fetchAll($sql, ['uid' => $userId, 'lim' => $limit]) ?: [];
    }

    /**
     * Количество новых читателей (уникальных пар user+story) по дням за N дней.
     * В story_views одна строка на пару (user, story), поэтому created_at —
     * это дата первого прочтения.
     */
    public function getReadersByDay(int $userId, int $days = 30): array
    {
        $sql = "
            SELECT DATE_FORMAT(sv.created_at, '%Y-%m-%d') AS label,
                   COUNT(*) AS value
            FROM `story_views` sv
            JOIN `stories` s ON s.id = sv.story_id
            WHERE s.user_id = :uid AND s.deleted_at IS NULL
              AND sv.created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY label
            ORDER BY label ASC
        ";

        return $this->db->fetchAll($sql, ['uid' => $userId, 'days' => (int)$days]) ?: [];
    }

    /**
     * Разбивка читателей по источникам перехода.
     * Данные наполняются только для новых просмотров (после внедрения трекинга referrer).
     */
    public function getTrafficSources(int $userId): array
    {
        $sql = "
            SELECT sv.referrer_type AS label,
                   COUNT(*) AS value
            FROM `story_views` sv
            JOIN `stories` s ON s.id = sv.story_id
            WHERE s.user_id = :uid AND s.deleted_at IS NULL
            GROUP BY sv.referrer_type
            ORDER BY value DESC
        ";

        return $this->db->fetchAll($sql, ['uid' => $userId]) ?: [];
    }

    public function getTrafficSourceNames(): array
    {
        return [
            'internal' => 'Внутренние',
            'external' => 'Внешние сайты',
            'search'   => 'Поиск',
            'social'   => 'Соцсети',
            'direct'   => 'Прямые',
        ];
    }

    /**
     * Динамика статьи: число активных читателей (уникальных пользователей)
     * за указанный период (в днях от сегодня).
     */
    public function getStoryActiveReaders(int $storyId, int $days): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(DISTINCT sv.user_id) FROM `story_views` sv
             WHERE sv.story_id = :sid
               AND sv.updated_at >= DATE_SUB(NOW(), INTERVAL :days DAY)",
            ['sid' => (int)$storyId, 'days' => (int)$days]
        );
    }
}