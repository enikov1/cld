<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$url = 'https://image.tmdb.org/t/p/w500/y8hcR1R8QmGs8uLHQhIFHgCFWDd.jpg';

$tests = [
    'default' => ['User-Agent' => 'LordSerialBot/1.0'],
    'referer' => ['User-Agent' => 'LordSerialBot/1.0', 'Referer' => 'https://www.themoviedb.org/'],
];

foreach ($tests as $name => $headers) {
    $r = Illuminate\Support\Facades\Http::timeout(30)->withHeaders($headers)->get($url);
    echo $name . ': status=' . $r->status() . ' len=' . strlen($r->body()) . PHP_EOL;
}

$stored = app(App\Services\PosterStorage::class)->storeFromUrl(
    $url,
    App\Services\PosterContext::forSeriesData('tmdb-test', ['title' => 'Test', 'slug' => 'test']),
);
echo 'stored: ' . ($stored ?? 'NULL') . PHP_EOL;
