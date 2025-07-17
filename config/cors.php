<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['*'],             // Allow all HTTP methods

    'allowed_origins' => ['*'],             // Allow all origins

    'allowed_origins_patterns' => [],       // No specific pattern matching needed

    'allowed_headers' => ['*'],             // Allow all headers

    'exposed_headers' => ['Authorization', 'Content-Type'], // Allow token headers

    'max_age' => 0,                         // No caching of preflight response

    'supports_credentials' => false,        // ✅ KEEP false if not using cookies (token-based only)

];
