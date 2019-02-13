<?php

$router->any('/mailgun/{any}', ['uses' => 'Mailgun@dispatcher', 'as'=>config('mailgun.router.namedPrefix').'.mailgun']);

$router->any('/subscribe', ['uses' => 'Api@subscribe', 'as'=>config('mailgun.router.namedPrefix').'.api.subscribe']);
$router->get('/subscribe.js', ['uses' => 'Api@subscribeJsGet', 'as'=>config('mailgun.router.namedPrefix').'.api.subscribeJsGet']);
$router->post('/subscribe.js', ['uses' => 'Api@subscribeJsPost', 'as'=>config('mailgun.router.namedPrefix').'.api.subscribeJsPost']);

$router->any('/stats', ['uses' => 'Api@stats', 'as'=>config('mailgun.router.namedPrefix').'.api.stats']);
//$router->get('/campaign/delivery/{identifier}', ['uses' => 'Api@campaignDelivery']);

