<?php

$router->add('GET', 'about',   'PagesController@about',   'page.about');
$router->add('GET', 'privacy', 'PagesController@privacy', 'page.privacy');
$router->add('GET', 'rules',   'PagesController@rules',   'page.rules');
$router->add('GET', 'chat',    'PagesController@chat',    'page.chat');