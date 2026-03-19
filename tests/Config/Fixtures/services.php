<?php

return [
    'parameters' => [
        'database.host' => 'localhost',
        'database.port' => 3306,
        'app.debug' => true,
    ],
    'services' => [
        'mailer' => [
            'class' => 'App\\Mailer',
            'arguments' => ['%database.host%'],
        ],
    ],
];
