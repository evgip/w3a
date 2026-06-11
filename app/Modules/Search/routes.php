<?php

// Страница поиска (обрабатывает и вывод формы, и вывод результатов через GET-параметры)
$router->add('GET', 'search', 'SearchController@index', 'search.index');
