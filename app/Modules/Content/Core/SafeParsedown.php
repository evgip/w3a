<?php

namespace App\Modules\Content\Core;

/**
 * Расширение Parsedown с возможностью отключения изображений
 * 
 * Parsedown не имеет встроенного метода setImagesEnabled(),
 * поэтому мы переопределяем метод image() для блокировки картинок.
 */
class SafeParsedown extends \Parsedown
{
    /**
     * Разрешены ли изображения
     */
    private bool $imagesEnabled = true;

    /**
     * Включить/отключить изображения
     */
    public function setImagesEnabled(bool $enabled): void
    {
        $this->imagesEnabled = $enabled;
    }

    /**
     * Переопределяем обработку изображений
     * 
     * Если изображения отключены — возвращаем пустую строку
     * вместо HTML-тега <img>
     */
    protected function inlineImage($Excerpt): ?array
    {
        if (!$this->imagesEnabled) {
            // Пропускаем изображение — возвращаем null
            // Parsedown обработает это как обычный текст
            return null;
        }

        return parent::inlineImage($Excerpt);
    }
}