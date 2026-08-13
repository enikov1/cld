<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$r = App\Models\CronRun::find(5);
if ($r && $r->status === 'running') {
    App\Services\CronRunLogger::finish(
        $r,
        'failed',
        null,
        'Превышено время ожидания',
        'Процесс бэкапа был прерван (очистка).',
    );
    echo "marked id5 failed\n";
} else {
    echo 'id5 status: ' . ($r?->status ?? 'missing') . "\n";
}

echo 'resolvePhpBinary: ' . App\Support\ArtisanDetached::resolvePhpBinary() . PHP_EOL;
echo 'isJobRunning: ';
var_export(App\Services\CronRunLogger::isJobRunning('backup:run'));
echo PHP_EOL;
