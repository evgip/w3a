<?php

// ==========================================
// Публичный лог модерации (доступен модераторам и админам)
// ==========================================
$router->add('GET', 'mod/log', 'ModerationsController@log', 'mod.log');

// ==========================================
// Приватные заметки о пользователях
// ==========================================
$router->add('GET', 'mod/notes', 'ModerationsController@notes', 'mod.notes');
$router->add('POST', 'mod/notes/store', 'ModerationsController@storeNote', 'mod.notes.store');
$router->add('POST', 'mod/notes/{id}/delete', 'ModerationsController@deleteNote', 'mod.notes.delete');

// ==========================================
// Статистика активности модераторов
// ==========================================
$router->add('GET', 'mod/stats', 'ModerationsController@stats', 'mod.stats');

// Действия модератора
$router->add('POST', 'mod/ban/{id}', 'ModerationsController@banUser', 'mod.ban');