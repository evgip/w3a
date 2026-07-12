<?php

declare(strict_types=1);

namespace App\Modules\Stories\Services;

/**
 * Сервис для расчёта рейтингов и сортировки.
 * 
 * Содержит алгоритмы ранжирования:
 * - Wilson Score (доверительный интервал для рейтинга комментариев)
 * - Hotness (алгоритм "горячести" историй, похож на Reddit/HN)
 * 
 * Вынесен из helpers.php для:
 * - Тестируемости (можно мокать в unit-тестах)
 * - Чёткой области ответственности
 * - Возможности инъекции зависимостей
 */
class RankingService
{
    /**
     * Z-значение для 80% доверительного интервала
     * @see http://evanmiller.org/how-not-to-sort-by-average-rating.html
     */
    private const Z = 1.281551565545;
    
    /**
     * Эпоха Reddit/Lobsters (11 декабря 2005, 00:00:00 UTC)
     */
    private const EPOCH = 1134316800;

    /**
     * Вычисляет confidence score по формуле Вильсона (Wilson score interval)
     * 
     * @param int $score Рейтинг комментария (апвоты минус флаги)
     * @param int $flags Количество флагов
     * @return float Значение от 0 до 1
     * 
     * @throws \InvalidArgumentException Если n < 0
     * @see http://evanmiller.org/how-not-to-sort-by-average-rating.html
     * @see https://github.com/reddit/reddit/blob/master/r2/r2/lib/db/_sorts.pyx
     */
    public function wilsonScore(int $score, int $flags): float 
    {
        $ups = $score + $flags;
        $downs = $flags;
        $n = $ups + $downs;
        
        if ($n === 0) {
            return 0.0;
        }
        
        if ($n < 0) {
            throw new \InvalidArgumentException(
                "n should count number of upvotes + flags; that can't be a negative number"
            );
        }
        
        $z = self::Z;
        $p = $ups / $n;
        $zSquared = $z * $z;
        
        $left = $p + (1 / (2 * $n) * $zSquared);
        $right = $z * sqrt(($p * ((1 - $p) / $n)) + ($zSquared / (4 * $n * $n)));
        $under = 1.0 + ((1.0 / $n) * $zSquared);
        
        $confidence = ($left - $right) / $under;
        
        return max(0.0, min(1.0, $confidence));
    }

    /**
     * Вычисление "горячести" истории для сортировки в ленте.
     * 
     * Алгоритм похож на Reddit/HN:
     * - Новые истории с высоким рейтингом поднимаются выше
     * - Со временем "горячесть" падает
     * - Теги могут давать модификаторы
     * 
     * @param int $score Суммарный рейтинг (upvotes - downvotes)
     * @param string $createdAt Дата публикации (MySQL datetime)
     * @param array $tagHotnessMods Массив модификаторов тегов (float)
     * @return float Значение для сортировки (больше = выше)
     */
    public function calculateHotness(int $score, string $createdAt, array $tagHotnessMods = []): float
    {
        // 1. Сумма модификаторов тегов (base)
        $base = array_sum($tagHotnessMods);
        
        // 2. Логарифмический рейтинг
        $order = log10(max(abs($score), 1));
        $sign  = $score > 0 ? 1 : ($score < 0 ? -1 : 0);
        
        // 3. Секунды с эпохи Reddit/Lobsters
        $seconds = strtotime($createdAt) - self::EPOCH;
        
        // 4. Финальная формула с инверсией
        $hotness = (($sign * $order + $seconds / 45000) + $base);
        
        return round($hotness, 7);
    }
}