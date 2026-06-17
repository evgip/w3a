<?php
/**
 * Кнопка "Пожаловаться" — вставляется в story/comment partial
 * Ожидает переменные: $flagType ('story'|'comment'), $flagId (int)
 */
if (!Auth::check()) { return; }
?>
<a href="<?= route('flags.report', ['type' => $flagType, 'id' => $flagId]) ?>"
   class="flag-link"
   title="Пожаловаться на контент"
   onclick="return confirm('Вы уверены, что хотите подать жалобу?');">
    🚩
</a>