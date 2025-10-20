<?php

return [
    'paths' => ['api/*', 'login', 'logout', 'sanctum/csrf-cookie', 'user'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
        'http://localhost:3001',
        'https://brimsclient-ihpwt.ondigitalocean.app/',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'supports_credentials' => true,
];