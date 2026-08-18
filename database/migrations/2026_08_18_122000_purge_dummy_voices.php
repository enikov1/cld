<?php

use App\Services\TaxonomyService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(TaxonomyService::class)->purgeDummyVoices();
        \App\Support\TplCache::bumpGlobalVersion();
    }

    public function down(): void
    {
    }
};
