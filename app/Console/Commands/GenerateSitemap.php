<?php

namespace App\Console\Commands;

use App\Services\SitemapService;
use Illuminate\Console\Command;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate {--force : Regenerate even if sitemap is fresh}';

    protected $description = 'Generate sitemap.xml for public pages';

    public function handle(SitemapService $sitemap): int
    {
        if (!$this->option('force') && !$sitemap->shouldRegenerate()) {
            $this->line('Sitemap is up to date.');

            return self::SUCCESS;
        }

        $sitemap->generate();
        $this->info('Sitemap written to ' . $sitemap->path() . ' (' . $sitemap->urlCount() . ' URLs)');

        return self::SUCCESS;
    }
}
