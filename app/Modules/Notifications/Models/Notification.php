<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Core\Model;

class Notification extends Model
{
    protected string $table = 'user_notifications';

    protected array $fillable = [
        'notifiable_id',
        'user_id',
        'type',
        'notifiable_type',
        'actor_id',
        'message',
        'is_read',
        'read_at'
    ];

    // Типы уведомлений
    public const TYPE_REPLY = 'reply';
    public const TYPE_STORY_COMMENT = 'story_comment';  
    public const TYPE_MENTION = 'mention';
    public const TYPE_MESSAGE = 'message';
    public const TYPE_BAN = 'ban';
    public const TYPE_UNBAN = 'unban';
    public const TYPE_DEACTIVATED = 'deactivated';
    public const TYPE_ACTIVATED = 'activated';

    // Типы сущностей для полиморфной связи
    public const ENTITY_COMMENT = 'Comment';
    public const ENTITY_MESSAGE = 'Message';
    public const ENTITY_STORY = 'Story';

    /**
     * Создать уведомление об ответе на комментарий
     */
    public function createReplyNotification(
        int $userId,
        int $commentId,
        int $actorId,
        string $message = null
    ): int {
        return $this->createNotification(
            $userId,
            self::TYPE_REPLY,
            self::ENTITY_COMMENT,
            $commentId,
            $actorId,
            $message ?? 'Ответил на ваш комментарий'
        );
    }

    /**
     * Создать уведомление о комментарии к посту
     */
    public function createStoryCommentNotification(
        int $userId,
        int $commentId,
        int $actorId,
        string $message = null
    ): int {
        return $this->createNotification(
            $userId,
            self::TYPE_STORY_COMMENT,
            self::ENTITY_COMMENT,
            $commentId,
            $actorId,
            $message ?? 'Прокомментировал вашу публикацию'
        );
    }

    /**
     * Создать уведомление об упоминании
     */
    public function createMentionNotification(
        int $userId,
        int $commentId,
        int $actorId,
        string $message = null
    ): int {
        return $this->createNotification(
            $userId,
            self::TYPE_MENTION,
            self::ENTITY_COMMENT,
            $commentId,
            $actorId,
            $message ?? 'Упомянул вас в комментарии'
        );
    }

    /**
     * Создать уведомление о личном сообщении
     */
    public function createMessageNotification(
        int $userId,
        int $messageId,
        int $actorId,
        string $message = null
    ): int {
        return $this->createNotification(
            $userId,
            self::TYPE_MESSAGE,
            self::ENTITY_MESSAGE,
            $messageId,
            $actorId,
            $message ?? 'Отправил вам сообщение'
        );
    }

    /**
     * Создать уведомление о деактивации аккаунта
     */
    public function createDeactivatedNotification(int $userId, int $adminId): int
    {
        return $this->createNotification(
            $userId,
            self::TYPE_DEACTIVATED,
            'User',
            $userId,
            $adminId,
            'Ваша учетная запись была деактивирована администратором.'
        );
    }

    /**
     * Создать уведомление об активации аккаунта
     */
    public function createActivatedNotification(int $userId, int $adminId): int
    {
        return $this->createNotification(
            $userId,
            self::TYPE_ACTIVATED,
            'User',
            $userId,
            $adminId,
            'Ваша учетная запись была активирована администратором.'
        );
    }

    /**
     * Базовый метод создания уведомления
     */
    private function createNotification(
        int $userId,
        string $type,
        string $entityType,
        int $entityId,
        int $actorId,
        string $message
    ): int {
        // Защита от дубликатов
        $existing = $this->findFirst([
            'user_id' => $userId,
            'type' => $type,
            'notifiable_type' => $entityType,
            'notifiable_id' => $entityId
        ]);

        if ($existing) {
            return (int)$existing['id'];
        }

        return $this->create([
            'user_id' => $userId,
            'type' => $type,
            'notifiable_type' => $entityType,
            'notifiable_id' => $entityId,
            'actor_id' => $actorId,
            'message' => $message,
            'is_read' => 0
        ]);
    }

    /**
     * Найти первое уведомление по условиям
     */
    private function findFirst(array $conditions): ?array
    {
        $where = [];
        $params = [];

        foreach ($conditions as $key => $value) {
            $where[] = "`{$key}` = :{$key}";
            $params[$key] = $value;
        }

        $sql = "SELECT * FROM `{$this->table}` WHERE " . implode(' AND ', $where) . " LIMIT 1";
        
        // ✅ Используем $this->db->fetchOne()
        return $this->db->fetchOne($sql, $params);
    }

    /**
     * Получить уведомления пользователя с пагинацией и фильтрацией по типу
     */
    public function getUserNotifications(int $userId, ?string $type = null, int $limit = 25, int $page = 1): array
    {
        $where = "n.user_id = :user_id";
        $params = [
            'user_id' => $userId,
        ];

        if ($type && $type !== 'all') {
            $where .= " AND n.type = :type";
            $params['type'] = $type;
        }

        $sql = "
            SELECT
                n.*,
                u.username as actor_name,
                up.avatar as actor_avatar,
                c.comment as comment_text,
                c.story_id,
                s.title as story_title,
                m.message,
                m.conversation_id
            FROM `{$this->table}` n
            LEFT JOIN users u ON n.actor_id = u.id
            LEFT JOIN `user_profiles` up ON u.id = up.user_id
            LEFT JOIN comments c ON n.notifiable_type = 'Comment' AND n.notifiable_id = c.id
            LEFT JOIN stories s ON c.story_id = s.id
            LEFT JOIN messages m ON n.notifiable_type = 'Message' AND n.notifiable_id = m.id
            WHERE {$where}
            ORDER BY n.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        // ✅ Используем prepare() для bindValue с LIMIT/OFFSET
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        if (isset($params['type'])) {
            $stmt->bindValue(':type', $params['type']);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Получить количество непрочитанных уведомлений
     */
    public function getUnreadCount(int $userId): int
    {
        // ✅ Используем $this->db->fetchColumn()
        return (int)$this->db->fetchColumn("
            SELECT COUNT(*) FROM `{$this->table}` 
            WHERE user_id = :user_id AND is_read = 0
        ", ['user_id' => $userId]);
    }

    /**
     * Получить количество непрочитанных уведомлений по типам
     */
    public function getUnreadCountByType(int $userId): array
    {
        // ✅ Используем $this->db->fetchAll()
        return $this->db->fetchAll("
            SELECT type, COUNT(*) as count 
            FROM `{$this->table}` 
            WHERE user_id = :user_id AND is_read = 0 
            GROUP BY type
        ", ['user_id' => $userId]);
    }

    /**
     * Пометить одно уведомление как прочитанное
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        return $this->update($notificationId, [
            'is_read' => 1,
            'read_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Пометить все уведомления пользователя как прочитанные
     */
    public function markAllAsRead(int $userId): bool
    {
        // ✅ Используем $this->db->execute()
        return $this->db->execute("
            UPDATE `{$this->table}` 
            SET is_read = 1, read_at = NOW() 
            WHERE user_id = :user_id AND is_read = 0
        ", ['user_id' => $userId]) > 0;
    }

    /**
     * Удалить старые уведомления (старше 30 дней)
     */
    public function cleanupOldNotifications(int $daysOld = 30): int
    {
        $stmt = $this->db->prepare("
            DELETE FROM `{$this->table}` 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
            AND is_read = 1
        ");
        $stmt->bindValue(':days', $daysOld, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }
    
    /**
     * Проверить, хочет ли пользователь получать уведомления определённого типа
     */
    public function userWantsNotification(int $userId, string $setting): bool
    {
        $allowedSettings = [
            'notify_on_reply',
            'notify_on_story_comment',
            'notify_on_message',
            'notify_on_mention',
            'email_notifications',
        ];

        if (!in_array($setting, $allowedSettings, true)) {
            return true;
        }

        try {
            // ✅ Используем $this->db->fetchOne()
            $result = $this->db->fetchOne("
                SELECT `{$setting}`
                FROM `user_settings`
                WHERE `user_id` = :user_id
                LIMIT 1
            ", ['user_id' => $userId]);

            if (!$result) {
                return true;
            }

            return (bool) $result[$setting];
            
        } catch (\Throwable $e) {
            if ($this->logger) {
                $this->logger->error("[NOTIFICATIONS] Error checking user settings: " . $e->getMessage());
            }
            return true; // При ошибке — уведомляем (безопасное поведение)
        }
    } 
}