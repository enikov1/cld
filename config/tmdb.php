<?php

return [
    'api_key' => env('TMDB_API_KEY', ''),
    'base_url' => env('TMDB_BASE_URL', 'https://api.themoviedb.org/3'),
    'request_timeout' => (int)env('TMDB_REQUEST_TIMEOUT', 20),
    'language' => env('TMDB_LANGUAGE', 'ru-RU'),
];
