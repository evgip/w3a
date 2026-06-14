<?php

// Messages Module - Core Dialogue Thread Routes
$router->add('GET', 'messages', 'MessagesController@index', 'messages.index');
$router->add('GET', 'messages/chat/{id}', 'MessagesController@showDialog', 'messages.dialog');
$router->add('POST', 'messages/send', 'MessagesController@sendMessage', 'messages.send.submit');

// Global trigger to start or look up an conversation straight using a profile button parameter
$router->add('POST', 'messages/start/{userId}', 'MessagesController@startConversation', 'messages.start');


 