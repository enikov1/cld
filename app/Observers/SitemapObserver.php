<?php

namespace App\Observers;

use App\Services\SitemapService;

class SitemapObserver
{
    public function saved(object $model): void
    {
        app(SitemapService::class)->markDirty();
    }

    public function deleted(object $model): void
    {
        app(SitemapService::class)->markDirty();
    }
}
