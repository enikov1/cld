<?php

return [
    'api_token' => env('ALLOHA_API_TOKEN', ''),
    'base_url' => env('ALLOHA_BASE_URL', 'https://apbugall.org'),
    'request_timeout' => (int)env('ALLOHA_REQUEST_TIMEOUT', 20),
    'bulk_batch_size' => (int)env('ALLOHA_BULK_BATCH_SIZE', 40),
];
