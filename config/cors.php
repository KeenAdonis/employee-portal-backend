<?php

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie', // ✅ IMPORTANT
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000', // frontend
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];