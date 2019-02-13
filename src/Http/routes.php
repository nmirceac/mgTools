<?php

//subscribe / unsubscribe / feedback / newsletter read
$router->any('/subscribe/{listIdentifier}', ['uses' => 'SubscriberController@subscribeList', 'as'=>config('mailgun.router.namedPrefix').'.subscribeList']);
$router->any('/subscribe/{listIdentifier}/{applicationIdentifier}', ['uses' => 'SubscriberController@subscribeListApplication', 'as'=>config('mailgun.router.namedPrefix').'.subscribeApplication']);

$router->any('/unsubscribe/{campaignIdentifier}', ['uses' => 'SubscriberController@unsubscribe', 'as'=>config('mailgun.router.namedPrefix').'.unsubscribe']);

$router->any('/feedback', ['uses' => 'SubscriberController@feedback', 'as'=>config('mailgun.router.namedPrefix').'.feedback']);
$router->any('/read/{campaignIdentifier}', ['uses' => 'SubscriberController@read', 'as'=>config('mailgun.router.namedPrefix').'.read']);


$router->group(['middleware' => config('mailgun.router.authMiddleware')], function ($router) {
//    $router->get('/{image}', ['uses'=>'ImageController@index', 'as'=>config('mailgun.router.namedPrefix').'.get']);
});
