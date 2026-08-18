<?php

namespace Tests\Unit;

use App\Models\Series;
use App\Models\Voice;
use App\Services\SitemapService;
use App\Services\TaxonomyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoiceTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    public function test_upsert_skips_dummy_player_labels(): void
    {
        $service = app(TaxonomyService::class);

        $this->assertNull($service->upsertVoice('Смотреть онлайн', 1));
        $this->assertNull($service->upsertVoice('Плеер 2', 2));
        $this->assertNull($service->upsertVoice('Трейлер', 3));
        $this->assertSame(0, Voice::query()->count());
    }

    public function test_upsert_reuses_alloha_id(): void
    {
        $service = app(TaxonomyService::class);

        $first = $service->upsertVoice('LostFilm', 10);
        $second = $service->upsertVoice('LostFilm HD', 10);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second?->id);
        $this->assertSame(1, Voice::query()->count());
        $this->assertSame('LostFilm', $first->fresh()->name);
    }

    public function test_sitemap_includes_coming_soon(): void
    {
        config(['app.url' => 'https://lordserial.net']);

        $path = storage_path('framework/testing-sitemap.xml');
        $service = new class($path) extends SitemapService {
            public function __construct(private string $testPath)
            {
            }

            public function path(): string
            {
                return $this->testPath;
            }
        };

        $this->assertTrue($service->generate());
        $xml = (string) file_get_contents($path);
        $this->assertStringContainsString('/skoro/', $xml);

        @unlink($path);
    }

    public function test_manual_voices_survive_alloha_resync(): void
    {
        $series = Series::query()->create([
            'kp_id' => 'voice-manual',
            'slug' => 'voice-manual',
            'title' => 'Test',
            'is_active' => true,
            'is_hidden' => false,
        ]);

        $service = app(TaxonomyService::class);
        $manual = $service->upsertVoice('Авторская', null);
        $this->assertNotNull($manual);
        $series->voices()->sync([$manual->id]);

        $service->syncSeriesVoicesFromTranslations($series, [
            ['id' => 10, 'name' => 'LostFilm'],
        ]);

        $names = $series->voices()->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['Авторская', 'LostFilm'], $names);
    }

    public function test_purge_removes_player_tab_labels(): void
    {
        $service = app(TaxonomyService::class);
        $keep = $service->upsertVoice('LostFilm', 10);
        Voice::query()->create([
            'slug' => 'pleer-2',
            'name' => 'Плеер 2',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        Voice::query()->create([
            'slug' => 'treiler',
            'name' => 'Трейлер',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->assertSame(2, $service->purgeDummyVoices());
        $this->assertTrue(Voice::query()->whereKey($keep?->id)->exists());
        $this->assertSame(['LostFilm'], Voice::query()->pluck('name')->all());
    }

    public function test_sync_voice_catalog_from_alloha_payload(): void
    {
        $result = app(TaxonomyService::class)->syncVoiceCatalog([
            ['id' => 10, 'name' => 'LostFilm'],
            ['id' => 20, 'name' => 'Coldfilm'],
            ['id' => 99, 'name' => 'Плеер 2'],
        ]);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(2, $result['total']);
        $this->assertEqualsCanonicalizing(
            ['Coldfilm', 'LostFilm'],
            Voice::query()->orderBy('name')->pluck('name')->all(),
        );
    }

    public function test_purge_unused_keeps_attached_voices(): void
    {
        $service = app(TaxonomyService::class);
        $series = Series::query()->create([
            'kp_id' => 'voice-unused',
            'slug' => 'voice-unused',
            'title' => 'Test',
            'is_active' => true,
            'is_hidden' => false,
        ]);

        $keep = $service->upsertVoice('LostFilm', 10);
        $service->upsertVoice('Orphan Studio', 99);
        $this->assertNotNull($keep);
        $series->voices()->sync([$keep->id]);

        $this->assertSame(1, $service->purgeUnusedVoices());
        $this->assertSame(['LostFilm'], Voice::query()->pluck('name')->all());
    }

    public function test_delete_all_voices_clears_pivot(): void
    {
        $service = app(TaxonomyService::class);
        $series = Series::query()->create([
            'kp_id' => 'voice-delete-all',
            'slug' => 'voice-delete-all',
            'title' => 'Test',
            'is_active' => true,
            'is_hidden' => false,
        ]);
        $voice = $service->upsertVoice('LostFilm', 10);
        $this->assertNotNull($voice);
        $series->voices()->sync([$voice->id]);

        $this->assertSame(1, $service->deleteAllVoices());
        $this->assertSame(0, Voice::query()->count());
        $this->assertSame(0, $series->voices()->count());
    }
}
