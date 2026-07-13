<?php

return [
    'themes_dir' => resource_path('tpl'),
    'default_theme' => env('TPL_DEFAULT_THEME', 'default'),
    'cache_ttl' => (int)env('TPL_CACHE_TTL', 300),
];

