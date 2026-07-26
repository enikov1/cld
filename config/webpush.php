<?php

return [

    /*
    |--------------------------------------------------------------------------
    | VAPID keys for Web Push
    |--------------------------------------------------------------------------
    |
    | If env keys are empty, App\Services\WebPushService will generate and
    | persist a key pair into storage/app/vapid.json on first use.
    |
    */

    'vapid' => [
        'subject' => env('VAPID_SUBJECT', env('APP_URL', 'https://localhost')),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

];
