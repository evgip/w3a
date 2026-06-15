<?php

// === Система приглашений ===

// Управление приглашениями (для авторизованных пользователей)
$router->add('GET', 'invitations', 'InvitationsController@index', 'invitations.index');
$router->add('POST', 'invitations/create', 'InvitationsController@create', 'invitations.create');
$router->add('POST', 'invitations/revoke/{id}', 'InvitationsController@revoke', 'invitations.revoke');

// Регистрация по приглашению
$router->add('GET', 'register/invite/{code}', 'InvitationsController@showInviteRegistration', 'invitations.register.form');
$router->add('POST', 'register/invite/{code}', 'InvitationsController@registerWithInvite', 'invitations.register.submit');

// Запрос приглашения (для незарегистрированных)
$router->add('GET', 'invite/request', 'InvitationsController@showRequestForm', 'invitations.request.form');
$router->add('POST', 'invite/request', 'InvitationsController@submitRequest', 'invitations.request.submit');

// Админка запросов приглашений
$router->add('GET', 'admin/invitations/requests', 'InvitationsController@adminRequests', 'admin.invitations.requests');
$router->add('POST', 'admin/invitations/approve/{id}', 'InvitationsController@approveRequest', 'admin.invitations.approve');
$router->add('POST', 'admin/invitations/reject/{id}', 'InvitationsController@rejectRequest', 'admin.invitations.reject');