<?php

return [
    'router'=> [
        'includeRoutes'=>true,
        'prefix'=>'mgtools',
        'namedPrefix'=>'mg-tools',
        'guestMiddleware'=>'web',
        'authMiddleware'=>'auth'
    ],

    'api' => [
        'endpoint' => 'api.mailgun.net',
        'version' => 'v3',
        'ssl' => true
    ],

    'domain' => env('MAILGUN_DOMAIN', ''),
    'api_key' => env('MAILGUN_PRIVATE', ''),
    'public_api_key' => env('MAILGUN_PUBLIC', ''),
    'from' => [
        'address' => env('MAILGUN_FROM_ADDRESS', ''),
        'name' => env('MAILGUN_FROM_NAME', ''),
    ],
    'reply_to' => env('MAILGUN_REPLY_TO', ''),
    'force_from_address' => env('MAILGUN_FORCE_FROM_ADRESS', false),
    'catch_all' => env('MAILGUN_CATCH_ALL', ''),
    'testmode' => env('MAILGUN_TESTMODE', false)

];

