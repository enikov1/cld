<?php

return [
    'api_token' => env('ALLOHA_API_TOKEN', ''),
    'base_url' => env('ALLOHA_BASE_URL', 'https://apbugall.org'),
    'request_timeout' => (int)env('ALLOHA_REQUEST_TIMEOUT', 20),
    'bulk_batch_size' => (int)env('ALLOHA_BULK_BATCH_SIZE', 40),
    'voice_bulk_batch_size' => (int)env('ALLOHA_VOICE_BULK_BATCH_SIZE', 5),
    'voice_bulk_time_budget' => (int)env('ALLOHA_VOICE_BULK_TIME_BUDGET', 18),
    'voice_request_timeout' => (int)env('ALLOHA_VOICE_REQUEST_TIMEOUT', 8),
];
