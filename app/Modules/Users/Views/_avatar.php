<?php
/**
 * Компонент аватара
 * 
 * @var string|null $filename  - имя файла аватара
 * @var string|null $name      - имя пользователя (для плейсхолдера)
 * @var string      $sizeClass - модификатор размера ('avatar-sm', 'avatar-md' и т.д.)
 */

$sizeClass = $sizeClass ?? '';

if (!empty($filename)): ?>
    <img src="/uploads/avatars/<?= substr($filename, 0, 2) ?>/<?= e($filename) ?>" 
         class="avatar <?= $sizeClass ?>" alt="">
<?php else: ?>
    <span class="avatar avatar-placeholder <?= $sizeClass ?>">
        <?= e(mb_substr($name ?? '?', 0, 1)) ?>
    </span>
<?php endif; ?>