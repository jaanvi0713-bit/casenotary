<?php

return [
    'app_name'    => 'Notary Management System',
    'app_url'     => 'http://localhost/casemanagement/admin',
    'client_url'  => 'http://localhost/casemanagement/client',
    'timezone'    => 'America/New_York',
    'debug'       => true,

    'currency' => [
        'code'   => 'INR',
        'symbol' => 'Rs',
        'locale' => 'en-IN',
    ],

    'session' => [
        'name'     => 'NOTARY_SESSION',
        'lifetime' => 7200,
    ],

    'upload' => [
        'max_size'      => 10 * 1024 * 1024,
        'allowed_types' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip'],
        'path'          => __DIR__ . '/../uploads/',
    ],

    'security' => [
        'csrf_token_name' => '_csrf_token',
    ],
];
