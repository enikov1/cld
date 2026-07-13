<?php

return [
    // Fallback if not set in admin Settings → kinopoisk_api_key
    'api_key' => env('KINOPOISK_API_KEY', ''),
    'base_url' => env('KINOPOISK_BASE_URL', 'https://kinopoiskapiunofficial.tech/api'),
    'request_timeout' => (int)env('KINOPOISK_REQUEST_TIMEOUT', 20),
];

