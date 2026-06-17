<?php

// POST-маршруты (AJAX) — имена тоже нужны для единообразия
$router->add('POST', 'filters/add',    'TagsController@addFilter',    'tags.filters.add');
$router->add('POST', 'filters/remove', 'TagsController@removeFilter', 'tags.filters.remove');

// GET-маршруты
$router->add('GET', 'filters',         'TagsController@filters',      'tags.filters');
$router->add('GET', 'tags',            'TagsController@index',        'tags.index');
$router->add('GET', 'tags/{tagname}',  'TagsController@show',         'tags.show');

// === Categories ===
$router->add('GET', 'categories/{slug}', 'TagsController@categoriesShow', 'categories.show');