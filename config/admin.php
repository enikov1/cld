<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin panel access
    |--------------------------------------------------------------------------
    |
    | ADMIN_TOKEN is the master credential (role: full). Additional scoped tokens
    | live in admin_tokens (hash only). Login accepts master or any active token;
    | after login an opaque session cookie is issued (not the raw token).
    | Must be read via config() — not env() — when config is cached in production.
    |
    */

    'token' => env('ADMIN_TOKEN', ''),

    'path' => env('ADMIN_PATH', ''),

    'session_store' => env('ADMIN_SESSION_STORE', 'admin'),

];
