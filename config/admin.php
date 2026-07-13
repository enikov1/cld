<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin panel access
    |--------------------------------------------------------------------------
    |
    | ADMIN_TOKEN is sent by the React admin UI in the X-ADMIN-TOKEN header.
    | Must be read via config() — not env() — when config is cached in production.
    |
    */

    'token' => env('ADMIN_TOKEN', ''),

    'path' => env('ADMIN_PATH', ''),

];
