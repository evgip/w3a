<?php

declare(strict_types=1);

namespace App\Modules\Suggestions\Models;

use App\Core\Model;

/**
 * Модель для лога изменений контента.
 * 
 * Хранит историю всех примененных изменений к статьям и комментариям.
 * Записи создаются автоматически при достижении кворума сообществом 
 * или при ручном одобрении модератором.
 * 
 * @property int $id
 * @property string $target_type Тип контента ('Story' или 'Comment')
 * @property int $target_id ID контента
 * @property int|null $actor_id ID пользователя, применившего изменения (NULL = сообщество/кворум)
 * @property string $action_text Человекочитаемое описание изменения
 * @property bool $is_community_action Флаг, что изменение применено кворумом
 * @property string $created_at
 * @property string|null $deleted_at
 */
class ContentLog extends Model
{
    protected string $table = 'content_logs';
    
    protected array $fillable = [
        'target_type',
        'target_id',
        'actor_id',
        'action_text',
        'is_community_action',
        'created_at',
		'deleted_at' 
    ];
    
    // =========================================================================
    // ОСНОВНЫЕ МЕТОДЫ
    // =========================================================================
    
    /**
     * Получить историю изменений для конкретного контента.
     * 
     * Возвращает записи лога с именами пользователей (если действие выполнено не сообществом).
     * Используется для отображения блока "История изменений" на странице статьи/комментария.
     * 
     * @param string $targetType Тип контента ('Story' или 'Comment')
     * @param int $targetId ID контента
     * @param int $limit Максимальное количество записей (по умолчанию 50)
     * @return array Массив записей лога
     */
    public function getChangeLog(string $targetType, int $targetId, int $limit = 50): array
    {
        $sql = "SELECT cl.*, u.username AS actor_name
                FROM `{$this->table}` cl
                LEFT JOIN `users` u ON cl.actor_id = u.id
                WHERE cl.target_type = :target_type
                AND cl.target_id = :target_id
                AND cl.deleted_at IS NULL
                ORDER BY cl.created_at DESC
                LIMIT :limit";
        
        $stmt = static::db()->prepare($sql);
        $stmt->bindValue(':target_type', $targetType);
        $stmt->bindValue(':target_id', $targetId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Получить историю изменений с пагинацией.
     * 
     * Используется для админ-панели или профиля пользователя, 
     * где требуется разбивка по страницам.
     * 
     * @param string $targetType Тип контента
     * @param int $targetId ID контента
     * @param int $limit Количество записей на странице
     * @param int $offset Смещение
     * @return array Массив записей лога
     */
    public function getChangeLogPaginated(string $targetType, int $targetId, int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT cl.*, u.username AS actor_name
                FROM `{$this->table}` cl
                LEFT JOIN `users` u ON cl.actor_id = u.id
                WHERE cl.target_type = :target_type
                AND cl.target_id = :target_id
                AND cl.deleted_at IS NULL
                ORDER BY cl.created_at DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = static::db()->prepare($sql);
        $stmt->bindValue(':target_type', $targetType);
        $stmt->bindValue(':target_id', $targetId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Подсчитать количество записей лога для конкретного контента.
     * 
     * Используется для расчета пагинации.
     * 
     * @param string $targetType Тип контента
     * @param int $targetId ID контента
     * @return int Количество записей
     */
    public function countByTarget(string $targetType, int $targetId): int
    {
        $sql = "SELECT COUNT(*) FROM `{$this->table}` 
                WHERE target_type = :target_type 
                AND target_id = :target_id 
                AND deleted_at IS NULL";
        
        $stmt = static::db()->prepare($sql);
        $stmt->bindValue(':target_type', $targetType);
        $stmt->bindValue(':target_id', $targetId, \PDO::PARAM_INT);
        $stmt->execute();
        
        return (int) $stmt->fetchColumn();
    }
    
    // =========================================================================
    // МЕТОДЫ ДЛЯ АДМИНИСТРИРОВАНИЯ И СТАТИСТИКИ
    // =========================================================================
    
    /**
     * Получить последние изменения, выполненные конкретным пользователем.
     * 
     * Используется для профиля пользователя или проверки активности модераторов.
     * Учитывает как личные правки, так и одобрения предложений.
     * 
     * @param int $actorId ID пользователя/модератора
     * @param int $limit Максимальное количество записей
     * @return array Массив записей лога
     */
    public function getLogsByActor(int $actorId, int $limit = 30): array
    {
        $sql = "SELECT cl.*, u.username AS actor_name
                FROM `{$this->table}` cl
                LEFT JOIN `users` u ON cl.actor_id = u.id
                WHERE cl.actor_id = :actor_id
                AND cl.deleted_at IS NULL
                ORDER BY cl.created_at DESC
                LIMIT :limit";
        
        $stmt = static::db()->prepare($sql);
        $stmt->bindValue(':actor_id', $actorId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Получить сводную статистику по типам контента.
     * 
     * Возвращает количество примененных изменений, разделенных по типу 
     * и источнику (сообщество vs модератор).
     * 
     * @return array Статистика: ['Story' => [...], 'Comment' => [...]]
     */
    public function getStatsSummary(): array
    {
        $sql = "SELECT 
                    target_type,
                    COUNT(*) AS total,
                    SUM(CASE WHEN is_community_action = 1 THEN 1 ELSE 0 END) AS by_community,
                    SUM(CASE WHEN is_community_action = 0 THEN 1 ELSE 0 END) AS by_moderator
                FROM `{$this->table}`
                WHERE deleted_at IS NULL
                GROUP BY target_type";
        
        $stmt = static::db()->query($sql);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $stats = [];
        foreach ($rows as $row) {
            $stats[$row['target_type']] = [
                'total' => (int) $row['total'],
                'by_community' => (int) $row['by_community'],
                'by_moderator' => (int) $row['by_moderator']
            ];
        }
        
        return $stats;
    }
    
    /**
     * Получить последние глобальные изменения контента (для админ-дашборда).
     * 
     * @param int $limit Максимальное количество записей
     * @return array Массив записей лога
     */
    public function getRecentGlobalChanges(int $limit = 50): array
    {
        $sql = "SELECT cl.*, u.username AS actor_name
                FROM `{$this->table}` cl
                LEFT JOIN `users` u ON cl.actor_id = u.id
                WHERE cl.deleted_at IS NULL
                ORDER BY cl.created_at DESC
                LIMIT :limit";
        
        $stmt = static::db()->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}