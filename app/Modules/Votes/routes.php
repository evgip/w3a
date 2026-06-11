<?php

// Единая защищенная точка приема голосов для всего фреймворка
// $router->add('POST', 'vote/{type}/{id}', 'VotesController@handle', 'vote');

// Unified endpoint intercepting action types and vote direction codes
$router->add('POST', 'vote/{type}/{id}/{direction}', 'VotesController@handle', 'votes.toggle');