<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin panel access
    |--------------------------------------------------------------------------
    |
    | ADMIN_TOKEN authenticates the React admin UI via X-ADMIN-TOKEN header.
    | After login, an opaque session cookie is issued (not the raw token).
    | Must be read via config() — not env() — when config is cached in production.
    |
    */

    'token' => env('ADMIN_TOKEN', ''),

    'path' => env('ADMIN_PATH', ''),

];
