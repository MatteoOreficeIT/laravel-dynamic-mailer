<?php
return [
    'custom' => [
        'driver' => 'smtp',
        'host' => 'smtp.mailgun.org',
        'port' => 25,
        'from' => [
            'address' => 'hello@example.com',
            'name' => 'Example',
        ],
        'encryption' => 'tls',
        'username' => 'myuser',
        'password' => 'empty',
    ]
];