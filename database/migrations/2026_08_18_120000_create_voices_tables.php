<?php

use App\Models\Series;
use App\Services\TaxonomyService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voices', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->unsignedInteger('alloha_id')->nullable()->unique();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('seo_html')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_hidden')->default(false);
            $table->boolean('noindex')->default(true);
            $table->boolean('show_on_home')->default(false);
            $table->string('home_title')->nullable();
            $table->unsignedSmallInteger('home_item_limit')->default(18);
            $table->boolean('home_show_tabs')->default(true);
            $table->string('home_default_sort', 20)->default('latest');
            $table->timestamps();
        });

        Schema::create('series_voice', function (Blueprint $table) {
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->foreignId('voice_id')->constrained('voices')->cascadeOnDelete();
            $table->primary(['series_id', 'voice_id']);
        });

        $this->purgeDummyVoices();
        $this->backfillFromAllohaTranslations();
    }

    public function down(): void
    {
        Schema::dropIfExists('series_voice');
        Schema::dropIfExists('voices');
    }

    private function purgeDummyVoices(): void
    {
        app(TaxonomyService::class)->purgeDummyVoices();
    }

    private function backfillFromAllohaTranslations(): void
    {
        if (!Schema::hasTable('player_sources')) {
            return;
        }

        $service = app(TaxonomyService::class);
        $bySeries = [];

        $rows = DB::table('player_sources')
            ->select('series_id', 'alloha_translation_id', 'provider')
            ->whereNotNull('alloha_translation_id')
            ->whereNotNull('provider')
            ->get();

        foreach ($rows as $row) {
            $voice = $service->upsertVoice((string) $row->provider, (int) $row->alloha_translation_id);
            if (!$voice) {
                continue;
            }
            $bySeries[(int) $row->series_id][] = (int) $voice->id;
        }

        foreach ($bySeries as $seriesId => $voiceIds) {
            $series = Series::query()->find($seriesId);
            if (!$series) {
                continue;
            }
            $series->voices()->syncWithoutDetaching(array_values(array_unique($voiceIds)));
        }

        \App\Support\TplCache::bumpGlobalVersion();
        app(\App\Services\SitemapService::class)->markDirty();
    }
};
