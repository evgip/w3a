<?php

declare(strict_types=1);

namespace App\Modules\Votes\Models;

use App\Core\Model;
use App\Core\Logger;

/**
 * Модель для работы с голосами пользователей.
 * 
 * Поддерживает полиморфное голосование за истории и комментарии.
 * Использует транзакции для обеспечения целостности данных.
 */
class Vote extends Model
{
    protected string $table = 'votes';

    /**
     * Поля, доступные для массового присваивания.
     * Должны соответствовать реальной структуре таблицы votes:
     * - user_id: ID проголосовавшего пользователя
     * - votable_type: тип сущности ('story' или 'comment')
     * - votable_id: ID сущности
     * - vote_type: направление голоса (1 или -1)
     */
    protected array $fillable = [
        'user_id',
        'votable_type',
        'votable_id',
        'vote_type',
    ];

    /**
     * Whitelist разрешённых типов сущностей и соответствующих таблиц БД.
     * Защита от SQL-инъекций через имя таблицы.
     */
    private const ALLOWED_TYPES = [
        'story'   => 'stories',
        'comment' => 'comments',
    ];

    /**
     * Получить текущий голос пользователя за сущность.
     * 
     * @param int    $userId ID пользователя
     * @param string $type   Тип сущности ('story' или 'comment')
     * @param int    $id     ID сущности
     * @return int|null 1 (upvote), -1 (downvote) или null (нет голоса)
     */
    public function getUserVote(int $userId, string $type, int $id): ?int
    {
        if (!$this->isValidType($type)) {
            return null;
        }

        $stmt = static::db()->prepare(
            "SELECT `vote_type` 
             FROM `votes` 
             WHERE `user_id` = :uid 
               AND `votable_type` = :type 
               AND `votable_id` = :id 
             LIMIT 1"
        );
        $stmt->execute([
            'uid'  => $userId,
            'type' => $type,
            'id'   => $id,
        ]);
        
        $val = $stmt->fetchColumn();
        return $val !== false ? (int)$val : null;
    }

    /**
     * Переключить голос пользователя (создать/изменить/удалить).
     * 
     * Логика:
     * - Если голос совпадает с текущим → удалить голос (отмена)
     * - Если голос отличается → обновить направление
     * - Если голоса нет → создать новый
     * 
     * @param int    $userId    ID пользователя
     * @param string $type      Тип сущности ('story' или 'comment')
     * @param int    $id        ID сущности
     * @param int    $voteValue Направление (1 или -1)
     * @return bool true при успехе, false при ошибке
     */
    public function toggleVote(int $userId, string $type, int $id, int $voteValue): bool
    {
        // Валидация направления голоса
        if ($voteValue !== 1 && $voteValue !== -1) {
            Logger::warning('Недопустимое значение голоса', [
                'user_id' => $userId,
                'vote_value' => $voteValue,
            ]);
            return false;
        }

        // Проверка типа сущности (whitelist)
        if (!$this->isValidType($type)) {
            Logger::warning('Попытка голосования за недопустимый тип', [
                'user_id' => $userId,
                'type' => $type,
                'target_id' => $id,
            ]);
            return false;
        }

        $targetTable = self::ALLOWED_TYPES[$type];

        try {
            static::db()->beginTransaction();

            $existingVote = $this->getUserVote($userId, $type, $id);
            $scoreDelta = 0;

            if ($existingVote === $voteValue) {
                // CASE 1: Повторный клик на активную стрелку → отмена голоса
                $stmt = static::db()->prepare(
                    "DELETE FROM `votes` 
                     WHERE `user_id` = :uid 
                       AND `votable_type` = :type 
                       AND `votable_id` = :id"
                );
                $stmt->execute([
                    'uid'  => $userId,
                    'type' => $type,
                    'id'   => $id,
                ]);
                
                // Инвертируем исходное значение
                $scoreDelta = -$voteValue;
                
            } else {
                if ($existingVote !== null) {
                    // CASE 2: Смена направления голоса (например, с up на down)
                    $stmt = static::db()->prepare(
                        "UPDATE `votes` 
                         SET `vote_type` = :vval 
                         WHERE `user_id` = :uid 
                           AND `votable_type` = :type 
                           AND `votable_id` = :id"
                    );
                    $stmt->execute([
                        'vval' => $voteValue,
                        'uid'  => $userId,
                        'type' => $type,
                        'id'   => $id,
                    ]);
                    
                    // Двойная дельта (например, с +1 на -1 = -2)
                    $scoreDelta = $voteValue * 2;
                    
                } else {
                    // CASE 3: Новый голос
                    $stmt = static::db()->prepare(
                        "INSERT INTO `votes` 
                         (`user_id`, `votable_type`, `votable_id`, `vote_type`) 
                         VALUES (:uid, :type, :id, :vval)"
                    );
                    $stmt->execute([
                        'uid'  => $userId,
                        'type' => $type,
                        'id'   => $id,
                        'vval' => $voteValue,
                    ]);
                    
                    $scoreDelta = $voteValue;
                }
            }

            // Атомарное обновление score в целевой таблице
            if ($scoreDelta !== 0) {
                $stmt = static::db()->prepare(
                    "UPDATE `{$targetTable}` 
                     SET `score` = `score` + :delta 
                     WHERE `id` = :id"
                );
                $stmt->bindValue(':delta', $scoreDelta, \PDO::PARAM_INT);
                $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
                $stmt->execute();
            }

            static::db()->commit();
            return true;
            
        } catch (\Exception $e) {
            static::db()->rollBack();
            Logger::error('Ошибка транзакции голосования', [
                'user_id' => $userId,
                'type' => $type,
                'target_id' => $id,
                'vote_value' => $voteValue,
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Получить текущий score сущности.
     * 
     * @param string $type Тип сущности ('story' или 'comment')
     * @param int    $id   ID сущности
     * @return int Текущий score (0, если сущность не найдена)
     */
    public function getScoreForEntity(string $type, int $id): int
    {
        if (!$this->isValidType($type)) {
            return 0;
        }

        $targetTable = self::ALLOWED_TYPES[$type];
        
        $stmt = static::db()->prepare(
            "SELECT `score` 
             FROM `{$targetTable}` 
             WHERE `id` = :id 
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        
        $score = $stmt->fetchColumn();
        return $score !== false ? (int)$score : 0;
    }

    /**
     * Проверить, является ли тип сущности допустимым.
     * 
     * @param string $type Тип сущности
     * @return bool true, если тип разрешён
     */
    private function isValidType(string $type): bool
    {
        return isset(self::ALLOWED_TYPES[$type]);
    }
	
	/**
	 * Получить ID автора контента.
	 * 
	 * @param string $type Тип сущности ('story' или 'comment')
	 * @param int    $id   ID сущности
	 * @return int|null ID автора или null, если контент не найден
	 */
	public function getOwnerUserId(string $type, int $id): ?int
	{
		if (!$this->isValidType($type)) {
			return null;
		}

		$targetTable = self::ALLOWED_TYPES[$type];
		
		$stmt = static::db()->prepare(
			"SELECT `user_id` 
			 FROM `{$targetTable}` 
			 WHERE `id` = :id 
			 LIMIT 1"
		);
		$stmt->execute(['id' => $id]);
		
		$userId = $stmt->fetchColumn();
		return $userId !== false ? (int)$userId : null;
	}
}