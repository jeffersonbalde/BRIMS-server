<?php

return [
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'login',
        'logout', 
        'user',
        'admin/*',
        'incidents/*',
        'notifications/*',
        'population/*',
        'analytics/*',
        'reports/*'
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
        'http://localhost:3001',
        'https://brimsclient-ihpwt.ondigitalocean.app',
        'https://brimsclient-ihpwt.ondigitalocean.app/' // Try with trailing slash too
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'supports_credentials' => true,

    // Add these for better CORS handling
    'exposed_headers' => [],
    'max_age' => 0,
];