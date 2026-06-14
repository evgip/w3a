<?php

// Список всех уведомлений
$router->add('GET', 'notifications', 'NotificationsController@index', 'notifications.index');

// API endpoints
// $router->add('GET', 'api/notifications/unread', 'NotificationsController@unread', 'notifications.api.unread');
$router->add('GET', 'api/notifications/count', 'NotificationsController@getCount', 'notifications.api.count');

// Действия с уведомлениями
$router->add('POST', 'notifications/{id}/read', 'NotificationsController@markAsRead', 'notifications.read');
$router->add('POST', 'notifications/mark-all-as-read', 'NotificationsController@markAllAsRead', 'notifications.markAllAsRead');