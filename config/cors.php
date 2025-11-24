<?php

return [
    'paths' => [
        'api/*', 
        'sanctum/csrf-cookie',
        'login', 
        'logout', 
        'register',
        'user',
        'admin/*',
        'notifications/*',
        'incidents/*',
        'reports/*',
        'analytics/*',
        'population/*'
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://brimsclient-ihpwt.ondigitalocean.app',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:5173' // Vite dev server
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'Access-Control-Allow-Origin',
        'Access-Control-Allow-Credentials'
    ],

    'max_age' => 0,

    'supports_credentials' => true,
];