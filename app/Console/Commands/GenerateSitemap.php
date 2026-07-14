<?php

namespace App\Console\Commands;

use App\Models\CronRun;
use App\Services\CronRunLogger;
use App\Services\SitemapService;
use Illuminate\Console\Command;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate
        {--force : Regenerate even if sitemap is fresh}
        {--trigger= : schedule|admin|cli}
    ';

    protected $description = 'Generate sitemap.xml for public pages';

    public function handle(SitemapService $sitemap): int
    {
        if (!$this->option('force') && !$sitemap->shouldRegenerate()) {
            $this->line('Sitemap is up to date.');

            return self::SUCCESS;
        }

        $trigger = CronRunLogger::detectTrigger(
            $this->option('trigger') ?: ($this->option('force') ? null : CronRun::TRIGGER_SCHEDULE)
        );

        $run = CronRunLogger::run(
            CronRunLogger::JOB_SITEMAP,
            'sitemap:generate',
            $trigger,
            function () use ($sitemap) {
                $sitemap->generate();
                $urlCount = $sitemap->urlCount();

                return [
                    'status' => CronRun::STATUS_SUCCESS,
                    'counts' => ['urls' => $urlCount],
                    'message' => 'Sitemap: ' . $urlCount . ' URL → ' . $sitemap->path(),
                ];
            },
            ['force' => (bool)$this->option('force')],
            'Генерация sitemap.xml',
        );

        $this->info((string)$run->message);

        return self::SUCCESS;
    }
}
