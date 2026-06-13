<?php

namespace App\Core;

class SvgChart
{
    private int $width;
    private int $height;
    private int $padding;
    private string $color;
    private string $fillColor;

    public function __construct(
        int $width = 600,
        int $height = 200,
        int $padding = 40,
        string $color = '#ac130d',
        string $fillColor = 'rgba(172, 19, 13, 0.1)'
    ) {
        $this->width = $width;
        $this->height = $height;
        $this->padding = $padding;
        $this->color = $color;
        $this->fillColor = $fillColor;
    }

    /**
     * Генерирует линейный график (line chart)
     */
    public function lineChart(array $data, string $title = ''): string
    {
        if (empty($data)) {
            return '<svg width="' . $this->width . '" height="' . $this->height .
                '"><text x="50%" y="50%" text-anchor="middle" fill="var(--color-fg, #666)">Нет данных</text></svg>';
        }

        $values = array_column($data, 'value');
        $maxValue = max($values);
        $minValue = min($values);
        // Защита от деления на ноль, если все значения одинаковы
        $range = ($maxValue - $minValue) > 0 ? ($maxValue - $minValue) : 1;

        $chartWidth = $this->width - (2 * $this->padding);
        $chartHeight = $this->height - (2 * $this->padding);
        $dataCount = count($data);

        $points = [];
        $labels = [];

        foreach ($data as $index => $item) {
            // ЗАЩИТА ОТ ДЕЛЕНИЯ НА НОЛЬ
            $divisor = $dataCount > 1 ? ($dataCount - 1) : 1;

            if ($dataCount === 1) {
                // Если точка одна, размещаем её по центру
                $x = $this->padding + ($chartWidth / 2);
            } else {
                $x = $this->padding + ($index / $divisor) * $chartWidth;
            }

            $y = $this->padding + $chartHeight - (($item['value'] - $minValue) / $range) * $chartHeight;
            $points[] = [$x, $y];

            // Добавляем подписи для каждого 4-го элемента или последнего
            if ($index % 4 === 0 || $index === $dataCount - 1) {
                $labels[] = ['x' => $x, 'label' => $item['label']];
            }
        }

        // Создаём path для линии
        $pathData = 'M ' . $points[0][0] . ',' . $points[0][1];
        for ($i = 1; $i < $dataCount; $i++) {
            $pathData .= ' L ' . $points[$i][0] . ',' . $points[$i][1];
        }

        // Создаём path для заполненной области
        $areaPath = $pathData;
        $areaPath .= ' L ' . $points[$dataCount - 1][0] . ',' . ($this->height - $this->padding);
        $areaPath .= ' L ' . $points[0][0] . ',' . ($this->height - $this->padding);
        $areaPath .= ' Z';

        // Генерируем SVG
        $svg = '<svg width="' . $this->width . '" height="' . $this->height . '" ' .
            'viewBox="0 0 ' . $this->width . ' ' . $this->height . '" ' .
            'xmlns="http://www.w3.org/2000/svg" ' .
            'style="max-width: 100%; height: auto;">';

        if ($title) {
            $svg .= '<text x="' . ($this->width / 2) . '" y="20" text-anchor="middle" ' .
                'font-size="14" font-weight="bold" fill="var(--color-fg, #333)">' .
                e($title) . '</text>';
        }

        // Сетка (горизонтальные линии)
        $svg .= '<g stroke="var(--color-border, #e0e0e0)" stroke-width="1" stroke-dasharray="2,2">';
        for ($i = 1; $i <= 4; $i++) {
            $y = $this->padding + ($chartHeight * $i / 5);
            $svg .= '<line x1="' . $this->padding . '" y1="' . $y . '" ' .
                'x2="' . ($this->width - $this->padding) . '" y2="' . $y . '"/>';
        }
        $svg .= '</g>';

        // Оси
        $svg .= '<g stroke="var(--color-fg, #333)" stroke-width="1.5">';
        $svg .= '<line x1="' . $this->padding . '" y1="' . $this->padding . '" ' .
            'x2="' . $this->padding . '" y2="' . ($this->height - $this->padding) . '"/>';
        $svg .= '<line x1="' . $this->padding . '" y1="' . ($this->height - $this->padding) . '" ' .
            'x2="' . ($this->width - $this->padding) . '" y2="' . ($this->height - $this->padding) . '"/>';
        $svg .= '</g>';

        // Подписи оси Y
        $svg .= '<g font-size="10" fill="var(--color-fg, #666)" text-anchor="end">';
        for ($i = 0; $i <= 4; $i++) {
            $y = $this->padding + ($chartHeight * (4 - $i) / 4);
            $value = round($minValue + ($range * $i / 4));
            $svg .= '<text x="' . ($this->padding - 5) . '" y="' . ($y + 3) . '">' . $value . '</text>';
        }
        $svg .= '</g>';

        // Заполненная область
        $svg .= '<path d="' . $areaPath . '" fill="' . $this->fillColor . '"/>';

        // Линия графика
        $svg .= '<path d="' . $pathData . '" fill="none" stroke="' . $this->color .
            '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';

        // Точки на графике
        foreach ($points as $point) {
            $svg .= '<circle cx="' . $point[0] . '" cy="' . $point[1] . '" r="3" ' .
                'fill="' . $this->color . '"/>';
        }

        // Подписи оси X
        $svg .= '<g font-size="10" fill="var(--color-fg, #666)" text-anchor="middle">';
        foreach ($labels as $label) {
            $svg .= '<text x="' . $label['x'] . '" y="' . ($this->height - 10) . '">' .
                e($label['label']) . '</text>';
        }
        $svg .= '</g>';

        $svg .= '</svg>';

        return $svg;
    }

    /**
     * Генерирует столбчатую диаграмму (bar chart)
     */
    public function barChart(array $data, string $title = ''): string
    {
        if (empty($data)) {
            return '<svg width="' . $this->width . '" height="' . $this->height .
                '"><text x="50%" y="50%" text-anchor="middle" fill="var(--color-fg, #666)">Нет данных</text></svg>';
        }

        $values = array_column($data, 'value');
        $maxValue = max($values) > 0 ? max($values) : 1; // Защита от нуля

        $chartWidth = $this->width - (2 * $this->padding);
        $chartHeight = $this->height - (2 * $this->padding);
        $dataCount = count($data);

        // Защита от деления на ноль, если данных нет (хотя empty выше ловит это)
        $divisor = $dataCount > 0 ? $dataCount : 1;
        $barWidth = ($chartWidth / $divisor) * 0.7;
        $barGap = ($chartWidth / $divisor) * 0.3;

        $svg = '<svg width="' . $this->width . '" height="' . $this->height . '" ' .
            'viewBox="0 0 ' . $this->width . ' ' . $this->height . '" ' .
            'xmlns="http://www.w3.org/2000/svg" ' .
            'style="max-width: 100%; height: auto;">';

        if ($title) {
            $svg .= '<text x="' . ($this->width / 2) . '" y="20" text-anchor="middle" ' .
                'font-size="14" font-weight="bold" fill="var(--color-fg, #333)">' .
                e($title) . '</text>';
        }

        // Сетка
        $svg .= '<g stroke="var(--color-border, #e0e0e0)" stroke-width="1" stroke-dasharray="2,2">';
        for ($i = 1; $i <= 4; $i++) {
            $y = $this->padding + ($chartHeight * $i / 5);
            $svg .= '<line x1="' . $this->padding . '" y1="' . $y . '" ' .
                'x2="' . ($this->width - $this->padding) . '" y2="' . $y . '"/>';
        }
        $svg .= '</g>';

        // Оси
        $svg .= '<g stroke="var(--color-fg, #333)" stroke-width="1.5">';
        $svg .= '<line x1="' . $this->padding . '" y1="' . $this->padding . '" ' .
            'x2="' . $this->padding . '" y2="' . ($this->height - $this->padding) . '"/>';
        $svg .= '<line x1="' . $this->padding . '" y1="' . ($this->height - $this->padding) . '" ' .
            'x2="' . ($this->width - $this->padding) . '" y2="' . ($this->height - $this->padding) . '"/>';
        $svg .= '</g>';

        // Столбцы
        foreach ($data as $index => $item) {
            $x = $this->padding + ($index * ($barWidth + $barGap)) + ($barGap / 2);
            $barHeight = ($item['value'] / $maxValue) * $chartHeight;
            $y = $this->height - $this->padding - $barHeight;

            $svg .= '<rect x="' . $x . '" y="' . $y . '" width="' . $barWidth .
                '" height="' . $barHeight . '" fill="' . $this->color .
                '" rx="2"/>';

            if ($index % 4 === 0 || $index === $dataCount - 1) {
                $svg .= '<text x="' . ($x + $barWidth / 2) . '" y="' . ($this->height - 5) .
                    '" font-size="10" fill="var(--color-fg, #666)" text-anchor="middle">' .
                    e($item['label']) . '</text>';
            }
        }

        $svg .= '</svg>';

        return $svg;
    }
}
