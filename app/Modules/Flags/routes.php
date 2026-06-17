<?php

// ==================== ПУБЛИЧНЫЕ (для авторизованных) ====================
$router->add('GET',  'flags/report/{type}/{id}', 'FlagsController@reportForm', 'flags.report');
$router->add('POST', 'flags/report', 'FlagsController@submit',     'flags.submit');

// ==================== АДМИН-ПАНЕЛЬ ====================
$router->add('GET',  'admin/flags',              'FlagsController@adminIndex',   'admin.flags');
$router->add('POST', 'admin/flags/{id}/resolve', 'FlagsController@resolve',      'admin.flags.resolve');
$router->add('GET',  'admin/flags/count',        'FlagsController@pendingCount', 'admin.flags.count');