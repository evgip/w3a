<?php

namespace App\Modules\Search\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\Logger;

class SearchResult extends Model
{
    /**
     * High-performance full-text search across story articles
     */
    public function searchStories(string $keywords, string $sortBy = 'relevance'): array {
        $repo = new \App\Modules\Stories\Repositories\StoryRepository($this->db);
        
        $orderBy = ($sortBy === 'date') ? 's.id DESC' : 'relevance DESC, s.score DESC';
        
        return $repo->withAuthor()
                    ->withAvatar()
                    ->withTags()
                    ->addSelect("MATCH(s.title, s.description) AGAINST(:query_ft IN NATURAL LANGUAGE MODE) as relevance")
                    ->addWhere("s.deleted_at IS NULL")
                    ->addWhere("MATCH(s.title, s.description) AGAINST(:query_ft IN NATURAL LANGUAGE MODE)", [
                        ':query_ft' => $keywords
                    ])
                    ->setOrderBy($orderBy)
                    ->paginate(50, 0)
                    ->get();
    }

    /**
     * High-performance full-text search across recursive comments
     */
    public function searchComments(string $keywords, string $sortBy = 'relevance'): array
    {
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

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':query1', $keywords, \PDO::PARAM_STR);
        $stmt->bindValue(':query2', $keywords, \PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}