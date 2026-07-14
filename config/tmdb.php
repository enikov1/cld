<?php

return [
    'api_key' => env('TMDB_API_KEY', ''),
    'base_url' => env('TMDB_BASE_URL', 'https://api.themoviedb.org/3'),
    'image_base_url' => env('TMDB_IMAGE_BASE_URL', 'https://image.tmdb.org/t/p'),
    'logo_size' => env('TMDB_LOGO_SIZE', 'w500'),
    'request_timeout' => (int)env('TMDB_REQUEST_TIMEOUT', 20),
    'language' => env('TMDB_LANGUAGE', 'ru-RU'),
];
