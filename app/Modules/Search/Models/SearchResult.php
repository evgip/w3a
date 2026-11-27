<?php

declare(strict_types=1);

namespace App\Modules\Search\Models;

use W3a\Core\Database\Model;
use App\Modules\Stories\Repositories\StoryRepository;
use App\Modules\Comments\Repositories\CommentRepository;

class SearchResult extends Model
{
    /**
     * Универсальные базовые фильтры доступности.
     *
     * Применяются и к историям, и к комментариям, чтобы результаты поиска
     * были консистентны с основной лентой:
     *  - только опубликованные истории (status = published);
     *  - не удалённые сущности (deleted_at IS NULL);
     *  - исключение авторов-банов (mute), запрещённых для текущего пользователя.
     */
    private const STATUS_PUBLISHED = 'published';

    /**
     * High-performance full-text search across story articles.
     *
     * @param string $keywords  Поисковый запрос
     * @param string $sortBy    Порядок сортировки: 'date' либо 'relevance'
     * @param array  $mutedUserIds ID авторов, которых пользователь заблокировал (mute)
     */
    public function searchStories(string $keywords, string $sortBy = 'relevance', array $mutedUserIds = []): array
    {
        $repo = new StoryRepository($this->db);

        $orderBy = ($sortBy === 'date') ? 's.id DESC' : 'relevance DESC, s.score DESC';

        $repo->withAuthor()
             ->withAvatar()
             ->withTags()
             // Используем уникальное имя :query_ft_select / :query_ft_where,
             // чтобы не было коллизий биндингов в query-builder.
             ->addSelect("MATCH(s.title, s.description_text) AGAINST(:query_ft_select IN NATURAL LANGUAGE MODE) as relevance")
             // Консистентность с лентой: только опубликованные и не удалённые истории.
             ->addWhere("s.status = :search_status")
             ->addWhere("s.deleted_at IS NULL")
             ->addWhere("MATCH(s.title, s.description_text) AGAINST(:query_ft_where IN NATURAL LANGUAGE MODE)", [
                 ':query_ft_select' => $keywords,
                 ':query_ft_where'  => $keywords,
                 ':search_status'   => self::STATUS_PUBLISHED,
             ]);

        // Исключаем истории авторов-банов (mute) — так же, как в основной ленте.
        if (!empty($mutedUserIds)) {
            $repo->excludingUsers($mutedUserIds);
        }

        return $repo->setOrderBy($orderBy)
                    ->paginate(50, 0)
                    ->get();
    }

    /**
     * High-performance full-text search across recursive comments.
     *
     * @param string $keywords  Поисковый запрос
     * @param string $sortBy    Порядок сортировки: 'date' либо 'relevance'
     * @param array  $mutedUserIds ID авторов, которых пользователь заблокировал (mute)
     */
    public function searchComments(string $keywords, string $sortBy = 'relevance', array $mutedUserIds = []): array
    {
        $repo = new CommentRepository($this->db);

        $orderBy = ($sortBy === 'date') ? 'c.id DESC' : 'relevance DESC, c.score DESC';

        $repo->withAuthor()
             ->withAvatar()
             ->withStory()
             // Учитываем только не удалённые комментарии.
             ->onlyActive()
             // Доступность истории: только опубликованные и не удалённые истории.
             ->onlyPublishedStories()
             ->addSelect("MATCH(c.comment) AGAINST(:query_ft_select IN NATURAL LANGUAGE MODE) as relevance")
             ->addWhere("MATCH(c.comment) AGAINST(:query_ft_where IN NATURAL LANGUAGE MODE)", [
                 ':query_ft_select' => $keywords,
                 ':query_ft_where'  => $keywords,
             ]);

        // Исключаем комментарии авторов-банов (mute) — согласованно с общей лентой.
        if (!empty($mutedUserIds)) {
            $repo->excludingUsers($mutedUserIds);
        }

        return $repo->setOrderBy($orderBy)
                    ->paginate(50, 0)
                    ->get();
    }
}