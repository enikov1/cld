<?php

namespace App\Providers;

use App\Models\Collection;
use App\Models\Country;
use App\Models\Episode;
use App\Models\Genre;
use App\Models\Person;
use App\Models\Series;
use App\Models\Studio;
use App\Models\Year;
use App\Observers\EpisodeObserver;
use App\Observers\SitemapObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Episode::observe(EpisodeObserver::class);

        $sitemapObserver = SitemapObserver::class;
        foreach ([Collection::class, Country::class, Genre::class, Person::class, Series::class, Studio::class, Year::class] as $model) {
            $model::observe($sitemapObserver);
        }
    }
}
