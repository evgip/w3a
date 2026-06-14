<?php

namespace App\Modules\Search\Models;

use App\Core\Model;
use App\Core\Database;

class SearchResult extends Model
{
    /**
     * High-performance full-text search across story articles
     */
    public function searchStories(string $keywords, string $sortBy = 'relevance'): array
    {
        $db = Database::getConnection();
        $sql = "SELECT s.*, u.username as author_name, u.avatar as author_avatar,
                       GROUP_CONCAT(t.tag ORDER BY t.tag ASC) as tag_list,
                       MATCH(s.title, s.description) AGAINST(:query1 IN NATURAL LANGUAGE MODE) as relevance
                FROM `stories` s
                JOIN `users` u ON s.user_id = u.id
                LEFT JOIN `taggings` tg ON s.id = tg.story_id
                LEFT JOIN `tags` t ON tg.tag_id = t.id
                WHERE s.deleted_at IS NULL 
                  AND MATCH(s.title, s.description) AGAINST(:query2 IN NATURAL LANGUAGE MODE)";

        $sql .= " GROUP BY s.id";
        $sql .= ($sortBy === 'date') ? " ORDER BY s.id DESC" : " ORDER BY relevance DESC, s.score DESC";
        $sql .= " LIMIT 50";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':query1', $keywords, \PDO::PARAM_STR);
        $stmt->bindValue(':query2', $keywords, \PDO::PARAM_STR);
        $stmt->execute();

        $stories = $stmt->fetchAll();
        foreach ($stories as &$story) {
            $story['tags'] = !empty($story['tag_list']) ? explode(',', $story['tag_list']) : [];
        }
        return $stories;
    }

    /**
     * NEW: High-performance full-text search across recursive comments
     */
    public function searchComments(string $keywords, string $sortBy = 'relevance'): array
    {
        $db = Database::getConnection();

        // Pull comment contents joined with comment authors and their respective parent stories
        $sql = "SELECT c.*, u.username as author_name, u.avatar as author_avatar,
                       s.title as story_title,
                       MATCH(c.comment) AGAINST(:query1 IN NATURAL LANGUAGE MODE) as relevance
                FROM `comments` c
                JOIN `users` u ON c.user_id = u.id
                JOIN `stories` s ON c.story_id = s.id
                WHERE c.deleted_at IS NULL
                  AND MATCH(c.comment) AGAINST(:query2 IN NATURAL LANGUAGE MODE)";

        if ($sortBy === 'date') {
            $sql .= " ORDER BY c.id DESC";
        } else {
            $sql .= " ORDER BY relevance DESC, c.score DESC";
        }

        $sql .= " LIMIT 50";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':query1', $keywords, \PDO::PARAM_STR);
        $stmt->bindValue(':query2', $keywords, \PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}

