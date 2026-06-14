<?php

// Маршруты авторизации
$router->add('GET', 'login', 'UsersController@showLoginForm', 'auth.login');
$router->add('POST', 'login', 'UsersController@login', 'login.submit');
$router->add('GET', 'logout', 'UsersController@logout', 'auth.logout');


// Маршруты регистрации
$router->add('GET', 'register', 'UsersController@showRegisterForm', 'auth.register');
$router->add('POST', 'register', 'UsersController@register', 'register.submit');

// Список пользователей
$router->add('GET', 'users', 'UsersController@index', 'users');

// Профиль
$router->add('GET', 'user/{username}', 'UsersController@profile', 'user.profile');


// User Profile Workspace Management endpoints
$router->add('GET', 'account/settings', 'UsersController@settings', 'account.settings');
$router->add('POST', 'account/settings', 'UsersController@updateSettings', 'account.settings.submit');

$router->add('POST', 'account/settings/password', 'UsersController@updatePassword', 'account.password.submit');

$router->add('POST', 'account/notifications/read', 'UsersController@clearNotifications', 'account.notifications.read');


$router->add('GET', 'password/reset', 'UsersController@showRequestResetForm', 'password.request');
$router->add('POST', 'password/reset', 'UsersController@sendResetLink', 'password.request.submit');
$router->add('GET', 'password/reset/{token}', 'UsersController@showResetPasswordForm', 'password.reset');
$router->add('POST', 'password/reset/submit', 'UsersController@executePasswordReset', 'password.reset.submit');


// Named route handling incoming activation tokens links
$router->add('GET', 'register/activate/{token}', 'UsersController@activateAccount', 'auth.activate');
