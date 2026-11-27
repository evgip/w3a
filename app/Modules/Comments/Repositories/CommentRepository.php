<?php

declare(strict_types=1);

namespace App\Modules\Comments\Repositories;

use W3a\Core\Database\Database;

/**
 * Репозиторий для выборки данных комментариев (comments).
 *
 * Реализует тот же Fluent Interface (Query Builder), что и StoryRepository,
 * чтобы единообразно собирать JOIN-ы и условия для комментариев.
 *
 * Основные преимущества:
 * 1. Устраняет дублирование JOIN-ов (users, user_profiles, stories) в различных методах.
 * 2. Автоматически предотвращает дублирование таблиц в SQL-запросе.
 * 3. Обеспечивает консистентность фильтров (доступность истории, автор-баны)
 *    между поиском и основными списками.
 */
class CommentRepository
{
    private Database $db;

    /** @var array Список выбираемых полей (SELECT) */
    private array $selects = ['c.*'];

    /** @var array Список условий присоединения таблиц (JOIN) */
    private array $joins = [];

    /** @var array Реестр уже добавленных таблиц (для исключения дублей JOIN) */
    private array $joinedTables = [];

    /** @var array Условия фильтрации (WHERE) */
    private array $where = [];

    /** @var array Параметры для подготовленных выражений (PDO bindings) */
    private array $bindings = [];

    /** @var string Условие сортировки (ORDER BY) */
    private string $orderBy = '';

    /** @var int|null Лимит записей (LIMIT) */
    private ?int $limit = null;

    /** @var int|null Смещение записей (OFFSET) */
    private ?int $offset = null;

    /** @var string Основная таблица в FROM */
    private string $from = '`comments` c';

    /**
     * Конструктор репозитория.
     *
     * @param Database $db Экземпляр обертки над PDO для выполнения запросов
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Добавляет данные автора (username) к выборке.
     * Безопасен для повторного вызова: JOIN добавится только один раз.
     *
     * @return self
     */
    public function withAuthor(): self
    {
        if (!isset($this->joinedTables['users'])) {
            $this->selects[] = 'u.username as author_name';
            $this->joins[] = 'JOIN `users` u ON c.user_id = u.id';
            $this->joinedTables['users'] = true;
        }
        return $this;
    }

    /**
     * Добавляет аватар автора к выборке.
     * Автоматически гарантирует наличие JOIN с таблицей `users`.
     *
     * @return self
     */
    public function withAvatar(): self
    {
        if (!isset($this->joinedTables['user_profiles'])) {
            $this->selects[] = 'up.avatar as author_avatar';

            if (!isset($this->joinedTables['users'])) {
                $this->joins[] = 'JOIN `users` u ON c.user_id = u.id';
                $this->joinedTables['users'] = true;
            }

            $this->joins[] = 'LEFT JOIN `user_profiles` up ON u.id = up.user_id';
            $this->joinedTables['user_profiles'] = true;
        }
        return $this;
    }

    /**
     * Добавляет данные истории (story) к выборке комментария.
     * Итоговый JOIN всегда INNER: комментарий показываем только вместе
     * с доступной историей.
     *
     * @return self
     */
    public function withStory(): self
    {
        if (!isset($this->joinedTables['stories'])) {
            $this->selects[] = 's.title as story_title';
            $this->joins[] = 'JOIN `stories` s ON c.story_id = s.id';
            $this->joinedTables['stories'] = true;
        }
        return $this;
    }

    /**
     * Добавляет ограничение по доступности истории: только опубликованные
     * и не удалённые истории. Используется во всех местах выборки комментариев,
     * чтобы не показывать комментарии под недоступными историями.
     *
     * @return self
     */
    public function onlyPublishedStories(): self
    {
        $this->withStory(); // гарантируем JOIN c историями
        $this->where[] = "s.status = :story_status";
        $this->bindings[':story_status'] = 'published';
        $this->where[] = "s.deleted_at IS NULL";
        return $this;
    }

    /**
     * Добавляет ограничение по удалённым комментариям.
     *
     * @return self
     */
    public function onlyActive(): self
    {
        $this->where[] = "c.deleted_at IS NULL";
        return $this;
    }

    /**
     * Исключает комментарии авторов-банов (например, замьюченных пользователей).
     * Требует наличия JOIN с `users`.
     *
     * @param array $mutedUserIds Массив ID пользователей, чьи комментарии исключаются
     * @return self
     */
    public function excludingUsers(array $mutedUserIds): self
    {
        if (empty($mutedUserIds)) {
            return $this;
        }

        $this->withAuthor(); // гарантируем JOIN c users
        $inData = $this->db->buildInClause($mutedUserIds, 'muted_user');
        $this->where[] = "c.user_id NOT IN ({$inData['clause']})";
        $this->bindings = array_merge($this->bindings, $inData['bindings']);
        return $this;
    }

    /**
     * Добавляет дополнительное поле в SELECT.
     *
     * @param string $select SQL-выражение для выбора (например, "MATCH(...) as relevance")
     * @return self
     */
    public function addSelect(string $select): self
    {
        $this->selects[] = $select;
        return $this;
    }

    /**
     * Добавляет одно условие WHERE и соответствующие биндинги.
     *
     * @param string $condition SQL-условие
     * @param array $bindings Ассоциативный массив параметров для PDO
     * @return self
     */
    public function addWhere(string $condition, array $bindings = []): self
    {
        if (!empty($condition)) {
            $this->where[] = $condition;
            $this->bindings = array_merge($this->bindings, $bindings);
        }
        return $this;
    }

    /**
     * Устанавливает порядок сортировки результатов.
     *
     * @param string $orderBy SQL-выражение сортировки
     * @return self
     */
    public function setOrderBy(string $orderBy): self
    {
        $this->orderBy = $orderBy;
        return $this;
    }

    /**
     * Устанавливает ограничения пагинации.
     *
     * @param int $limit Количество записей
     * @param int $offset Смещение начала выборки (по умолчанию 0)
     * @return self
     */
    public function paginate(int $limit, int $offset = 0): self
    {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }

    /**
     * Собирает финальную SQL-строку из накопленных компонентов.
     *
     * @return string Готовый SQL-запрос
     */
    private function buildSql(): string
    {
        $sql = "SELECT " . implode(', ', $this->selects)
             . " FROM " . $this->from;

        if (!empty($this->joins)) {
            $sql .= " " . implode(" ", $this->joins);
        }

        if (!empty($this->where)) {
            $sql .= " WHERE " . implode(" AND ", $this->where);
        }

        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . $this->orderBy;
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }

        return $sql;
    }

    /**
     * Выполняет запрос и возвращает массив результатов.
     * Автоматически подставляет :limit и :offset в биндинги, если пагинация была задана.
     *
     * @return array Массив ассоциативных массивов с данными комментариев
     */
    public function get(): array
    {
        $sql = $this->buildSql();
        $bindings = $this->bindings;

        if ($this->limit !== null) {
            $bindings[':limit'] = $this->limit;
            $bindings[':offset'] = $this->offset;
        }

        return $this->db->fetchAll($sql, $bindings);
    }
}