<?php

// Serve the main public feed/homepage feed tier
$router->add('GET', '/', 'StoriesController@index', 'home');


// Secure story creation routes
$router->add('GET', 'stories/create', 'StoriesController@showCreateForm', 'story.form');
$router->add('POST', 'stories/create', 'StoriesController@create', 'story.create');

// Просмотр конкретной истории и комментариев к ней
$router->add('GET', 'story/{id}', 'StoriesController@show', 'story.show');

// Secure comment handling route
$router->add('POST', 'comments/create', 'StoriesController@addComment', 'comment.create');

// Редактирование, удаление и восстановление комментов
$router->add('POST', 'comments/{id}/edit', 'StoriesController@editComment', 'comment.edit');
$router->add('POST', 'comments/{id}/delete', 'StoriesController@deleteComment', 'comment.delete');
$router->add('POST', 'comments/{id}/restore', 'StoriesController@restoreComment', 'comment.restore');


$router->add('GET', 't/{tagname}', 'StoriesController@index', 'tags.filter');


// NEW: Story Editing operational routes
$router->add('GET', 'stories/{id}/edit', 'StoriesController@showEditForm', 'story.edit');
$router->add('POST', 'stories/{id}/edit', 'StoriesController@update', 'story.edit.submit');


$router->add('POST', 'admin/stories/{id}/delete', 'StoriesController@adminDelete', 'admin.story.delete');
$router->add('POST', 'admin/stories/{id}/restore', 'StoriesController@adminRestore', 'admin.story.restore');


// Добавьте эту строку в конец файла или рядом с маршрутом тегов
$router->add('GET', 'domain/{domain}', 'StoriesController@index', 'domains.show');