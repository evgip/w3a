<?php

// ==================== ПУБЛИЧНЫЕ МАРШРУТЫ ====================

// Публичная страница списка забаненных доменов
$router->add('GET', 'domains', 'OriginsController@index', 'domains.index');

// ==================== АДМИН-ПАНЕЛЬ (модераторы/админы) ====================

// Список всех доменов (админка)
$router->add('GET', 'admin/domains', 'OriginsController@adminIndex', 'admin.domains');

// Форма бана
$router->add('GET', 'admin/domains/create', 'OriginsController@showBanForm', 'admin.domains.create');

// Обработка бана
$router->add('POST', 'admin/domains/ban', 'OriginsController@ban', 'admin.domains.ban');

// Разбан домена
$router->add('POST', 'admin/domains/{id}/unban', 'OriginsController@unban', 'admin.domains.unban');
