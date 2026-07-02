<?php

namespace App\Modules\Search\Models;

use App\Core\Model;

class SearchResult extends Model
{
    /**
     * High-performance full-text search across story articles
     */
    public function searchStories(string $keywords, string $sortBy = 'relevance'): array
    {
        $sql = "SELECT s.*, u.username as author_name, up.avatar as author_avatar,
                       GROUP_CONCAT(t.slug ORDER BY t.slug ASC) as tag_list,
                       GROUP_CONCAT(CONCAT(t.slug, '||', t.name) ORDER BY t.slug ASC) as tags_combined,
                       MATCH(s.title, s.description) AGAINST(:query1 IN NATURAL LANGUAGE MODE) as relevance
                FROM `stories` s
                JOIN `users` u ON s.user_id = u.id
				LEFT JOIN `user_profiles` up ON u.id = up.user_id
                LEFT JOIN `taggings` tg ON s.id = tg.story_id
                LEFT JOIN `tags` t ON tg.tag_id = t.id
                WHERE s.deleted_at IS NULL 
                  AND MATCH(s.title, s.description) AGAINST(:query2 IN NATURAL LANGUAGE MODE)";

        $sql .= " GROUP BY s.id";
        $sql .= ($sortBy === 'date') ? " ORDER BY s.id DESC" : " ORDER BY relevance DESC, s.score DESC";
        $sql .= " LIMIT 50";

        $stmt = static::db()->prepare($sql);
        $stmt->bindValue(':query1', $keywords, \PDO::PARAM_STR);
        $stmt->bindValue(':query2', $keywords, \PDO::PARAM_STR);
        $stmt->execute();

        $stories = $stmt->fetchAll();
        foreach ($stories as &$story) {
            parseTagsCombined($story);
        }
        return $stories;
    }

    /**
     * NEW: High-performance full-text search across recursive comments
     */
    public function searchComments(string $keywords, string $sortBy = 'relevance'): array
    {
        // Pull comment contents joined with comment authors and their respective parent stories
        $sql = "SELECT c.*, u.username as author_name, up.avatar as author_avatar,
                       s.title as story_title,
                       MATCH(c.comment) AGAINST(:query1 IN NATURAL LANGUAGE MODE) as relevance
                FROM `comments` c
                JOIN `users` u ON c.user_id = u.id
				LEFT JOIN `user_profiles` up ON u.id = up.user_id
                JOIN `stories` s ON c.story_id = s.id
                WHERE c.deleted_at IS NULL
                  AND MATCH(c.comment) AGAINST(:query2 IN NATURAL LANGUAGE MODE)";

        if ($sortBy === 'date') {
            $sql .= " ORDER BY c.id DESC";
        } else {
            $sql .= " ORDER BY relevance DESC, c.score DESC";
        }

        $sql .= " LIMIT 50";

        $stmt = static::db()->prepare($sql);
        $stmt->bindValue(':query1', $keywords, \PDO::PARAM_STR);
        $stmt->bindValue(':query2', $keywords, \PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}

