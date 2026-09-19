<?php

declare(strict_types=1);

namespace App\Modules\Stories\Models;

use W3a\Core\Database\Model;
use W3a\Core\Database\Database;
use W3a\Core\Support\Logger;

class StoryView extends Model
{
    protected string $table = 'story_views';

    protected array $fillable = [
        'user_id',
        'story_id',
        'read_seconds',
        'referrer',
        'referrer_type',
    ];

    public function __construct(Database $db, Logger $logger)
    {
        parent::__construct($db, $logger);
    }

	/**
	 * Увеличить время чтения статьи (UPSERT).
	 * 
	 * Использует два разных именованных параметра (:seconds1 и :seconds2),
	 * так как PDO не позволяет переиспользовать один параметр в одном запросе.
	 * 
	 * @param int $userId ID пользователя
	 * @param int $storyId ID статьи
	 * @param int $seconds Сколько секунд добавить к счётчику
	 * @param string|null $referrer URL источника перехода (для статистики трафика)
	 * @param string $referrerType Тип источника (internal/external/search/social/direct)
	 */
	public function trackReadTime(int $userId, int $storyId, int $seconds, ?string $referrer = null, string $referrerType = 'direct'): void
	{
		if ($userId <= 0 || $storyId <= 0 || $seconds <= 0) {
			return;
		}

		if ($referrer !== null && mb_strlen($referrer) > 500) {
			$referrer = mb_substr($referrer, 0, 500);
		}

		try {
			$this->db->query("
				INSERT INTO `story_views` 
					(`user_id`, `story_id`, `read_seconds`, `referrer`, `referrer_type`, `created_at`, `updated_at`)
				VALUES 
					(:user_id, :story_id, :seconds1, :referrer, :referrer_type, NOW(), NOW())
				ON DUPLICATE KEY UPDATE 
					`read_seconds` = `read_seconds` + :seconds2,
					`updated_at` = NOW()
			", [
				'user_id'       => $userId,
				'story_id'      => $storyId,
				'seconds1'      => $seconds,  // ← для VALUES
				'seconds2'      => $seconds,  // ← для ON DUPLICATE KEY UPDATE
				'referrer'      => $referrer,
				'referrer_type' => $referrerType,
			]);
		} catch (\Exception $e) {
			$this->logger?->error("StoryView::trackReadTime failed", [
				'user_id'  => $userId,
				'story_id' => $storyId,
				'seconds'  => $seconds,
				'error'    => $e->getMessage(),
			]);
		}
	}

	/**
	 * Классифицирует referrer по типу источника.
	 *
	 * @param string|null $referrer URL источника перехода
	 * @param string $internalHost Хост нашего сайта (для internal)
	 * @return string internal|external|search|social|direct
	 */
	public function classifyReferrer(?string $referrer, string $internalHost = ''): string
	{
		if (empty($referrer)) {
			return 'direct';
		}

		$host = parse_url($referrer, PHP_URL_HOST) ?: '';

		// Внутренний переход (лента, теги, профиль и т.п.)
		if ($internalHost !== '' && $host !== '' && mb_strtolower($host) === mb_strtolower($internalHost)) {
			return 'internal';
		}

		// Поисковики
		if (preg_match('/(^|\.)(google|yandex|bing|mail\.ru|rambler|duckduckgo|yahoo|baidu)\./i', $host)) {
			return 'search';
		}

		// Соцсети и мессенджеры
		if (preg_match('/(^|\.)(vk\.com|ok\.ru|facebook\.com|instagram\.com|t\.me|telegram|twitter\.com|x\.com|youtube\.com|dzen\.ru|pinterest|reddit\.com|linkedin\.com)/i', $host)) {
			return 'social';
		}

		return 'external';
	}

    public function getUserTopTags(int $userId, int $limit = 10): array
    {
        if ($userId <= 0) {
            return [];
        }

        $sql = "
            SELECT 
                t.id as tag_id,
                t.slug,
                t.name,
                SUM(sv.read_seconds) as total_read_time,
                COUNT(DISTINCT sv.story_id) as stories_read
            FROM `story_views` sv
            JOIN `taggings` tg ON sv.story_id = tg.story_id
            JOIN `tags` t ON tg.tag_id = t.id
            WHERE sv.user_id = :user_id
            GROUP BY t.id, t.slug, t.name
            ORDER BY total_read_time DESC, stories_read DESC
            LIMIT :limit
        ";

        $stmt = $this->db->query($sql, ['user_id' => $userId, 'limit' => $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getViewedStoryIds(int $userId, int $limit = 100): array
    {
        if ($userId <= 0) {
            return [];
        }

        $sql = "
            SELECT `story_id` 
            FROM `story_views` 
            WHERE `user_id` = :user_id 
            ORDER BY `updated_at` DESC 
            LIMIT :limit
        ";

        $stmt = $this->db->query($sql, ['user_id' => $userId, 'limit' => $limit]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    }

public function getViewedStories(int $userId, int $limit = 50): array
    {
        if ($userId <= 0) {
            return [];
        }

        $sql = "
            SELECT s.id, s.title, s.description_text, s.score, s.reading_time, s.created_at, s.comments_count, s.has_paywall,
                   u.username as author_name, up.avatar as author_avatar,
                   sv.read_seconds, sv.updated_at as last_viewed
            FROM `story_views` sv
            JOIN `stories` s ON s.id = sv.story_id
            JOIN `users` u ON s.user_id = u.id
            LEFT JOIN `user_profiles` up ON u.id = up.user_id
            WHERE sv.user_id = :user_id AND s.deleted_at IS NULL AND s.status = 'published'
            ORDER BY sv.updated_at DESC
            LIMIT :limit
        ";

        $stmt = $this->db->query($sql, ['user_id' => $userId, 'limit' => $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}