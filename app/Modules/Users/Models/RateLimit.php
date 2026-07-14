<?php

namespace App\Modules\Users\Models;

use App\Core\Model;

class RateLimit extends Model
{
    protected string $table = 'rate_limits';

    // request_count и window_start будут заполняться автоматически через SQL
    protected array $fillable = [
        'identifier',
        'endpoint_action',
    ];

    /**
     * Атомарно увеличивает счетчик запросов для данного окна.
     * Если записи нет — создает её со счетчиком 1.
     * Если запись есть — увеличивает счетчик на 1.
     * 
     * @return int Новое значение счетчика после обновления
     */
    public function incrementRequestCount(string $identifier, string $action, int $windowSeconds): int
    {
        // Вычисляем начало временного окна прямо в PHP (округление вниз)
        // Например, для $windowSeconds = 60, все запросы с 10:01:00 по 10:01:59 получат одно и то же время
        $windowStart = date('Y-m-d H:i:s', floor(time() / $windowSeconds) * $windowSeconds);

        $sql = "INSERT INTO `{$this->table}` 
                    (`identifier`, `endpoint_action`, `window_start`, `request_count`)
                VALUES 
                    (:identifier, :action, :window_start, 1)
                ON DUPLICATE KEY UPDATE 
                    `request_count` = `request_count` + 1";

        // Теперь все плейсхолдеры уникальны, и ошибки не будет
        $this->db->execute($sql, [
            'identifier'   => $identifier,
            'action'       => $action,
            'window_start' => $windowStart,
        ]);

        // Возвращаем актуальное значение счетчика одним быстрым запросом
        return (int)$this->db->fetchColumn(
            "SELECT `request_count` FROM `{$this->table}` 
             WHERE `identifier` = :identifier 
               AND `endpoint_action` = :action 
               AND `window_start` = :window_start",
            [
                'identifier'   => $identifier,
                'action'       => $action,
                'window_start' => $windowStart,
            ]
        );
    }

    /**
     * Фоновая очистка устаревших окон (вызывать по крону или редко, а не случайно!)
     */
    public function clearStaleWindows(int $retentionSeconds): void
    {
        $this->db->execute(
            "DELETE FROM `{$this->table}` 
             WHERE `window_start` < NOW() - INTERVAL :retention SECOND",
            ['retention' => $retentionSeconds]
        );
    }
}