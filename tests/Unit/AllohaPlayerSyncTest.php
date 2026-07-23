<?php

namespace Tests\Unit;

use App\Models\PlayerSource;
use App\Models\Series;
use App\Models\SiteSetting;
use App\Services\AllohaPlayerSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllohaPlayerSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_remove_for_series_deletes_alloha_sources(): void
    {
        $series = Series::query()->create([
            'kp_id' => '999001',
            'slug' => 'alloha-remove-test',
            'title' => 'Test',
            'is_active' => true,
            'is_hidden' => false,
        ]);

        PlayerSource::query()->create([
            'series_id' => $series->id,
            'provider' => 'Dragon Money',
            'iframe_url' => 'https://example.com/player',
            'source_key' => AllohaPlayerSync::SOURCE_KEY,
            'is_active' => true,
            'priority' => 90,
        ]);

        PlayerSource::query()->create([
            'series_id' => $series->id,
            'provider' => 'Coldfilm',
            'iframe_url' => '<video-player data-title-id="999001"></video-player>',
            'source_key' => 'cdnvideohub',
            'is_active' => true,
            'priority' => 100,
        ]);

        app(AllohaPlayerSync::class)->removeForSeries($series);

        $this->assertSame(1, PlayerSource::query()->where('series_id', $series->id)->count());
        $this->assertSame(
            'cdnvideohub',
            PlayerSource::query()->where('series_id', $series->id)->value('source_key'),
        );
    }

    public function test_sync_skipped_when_disabled_in_settings(): void
    {
        SiteSetting::set('player_alloha_sync_enabled', '0');

        $series = Series::query()->create([
            'kp_id' => '999002',
            'slug' => 'alloha-disabled-test',
            'title' => 'Test',
            'is_active' => true,
            'is_hidden' => false,
        ]);

        PlayerSource::query()->create([
            'series_id' => $series->id,
            'provider' => 'Old Alloha',
            'iframe_url' => 'https://example.com/old',
            'source_key' => AllohaPlayerSync::SOURCE_KEY,
            'is_active' => true,
            'priority' => 100,
        ]);

        app(AllohaPlayerSync::class)->sync($series, [
            ['id' => 1, 'name' => 'New Voice', 'iframe' => 'https://example.com/new'],
        ]);

        $this->assertSame(0, PlayerSource::query()->where('series_id', $series->id)->count());
    }

    public function test_sync_preserves_existing_player_priority(): void
    {
        SiteSetting::set('player_alloha_sync_enabled', '1');

        $series = Series::query()->create([
            'kp_id' => '999003',
            'slug' => 'alloha-priority-preserve',
            'title' => 'Test',
            'is_active' => true,
            'is_hidden' => false,
        ]);

        PlayerSource::query()->create([
            'series_id' => $series->id,
            'provider' => 'LostFilm',
            'iframe_url' => 'https://example.com/lostfilm-old',
            'source_key' => AllohaPlayerSync::SOURCE_KEY,
            'alloha_translation_id' => 10,
            'is_active' => true,
            'priority' => 20,
        ]);

        PlayerSource::query()->create([
            'series_id' => $series->id,
            'provider' => 'Coldfilm',
            'iframe_url' => 'https://example.com/coldfilm-old',
            'source_key' => AllohaPlayerSync::SOURCE_KEY,
            'alloha_translation_id' => 20,
            'is_active' => true,
            'priority' => 50,
        ]);

        // API returns translations in reverse order vs manual tab order.
        app(AllohaPlayerSync::class)->sync($series, [
            ['id' => 10, 'name' => 'LostFilm', 'iframe' => 'https://example.com/lostfilm-new'],
            ['id' => 20, 'name' => 'Coldfilm', 'iframe' => 'https://example.com/coldfilm-new'],
        ]);

        $players = PlayerSource::query()
            ->where('series_id', $series->id)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $players);
        $this->assertSame(20, (int) $players[0]->alloha_translation_id);
        $this->assertSame(50, (int) $players[0]->priority);
        $this->assertSame('https://example.com/coldfilm-new', $players[0]->iframe_url);
        $this->assertSame(10, (int) $players[1]->alloha_translation_id);
        $this->assertSame(20, (int) $players[1]->priority);
        $this->assertSame('https://example.com/lostfilm-new', $players[1]->iframe_url);
    }

    public function test_sync_appends_new_players_after_existing(): void
    {
        SiteSetting::set('player_alloha_sync_enabled', '1');

        $series = Series::query()->create([
            'kp_id' => '999004',
            'slug' => 'alloha-priority-append',
            'title' => 'Test',
            'is_active' => true,
            'is_hidden' => false,
        ]);

        PlayerSource::query()->create([
            'series_id' => $series->id,
            'provider' => 'Coldfilm',
            'iframe_url' => 'https://example.com/coldfilm',
            'source_key' => AllohaPlayerSync::SOURCE_KEY,
            'alloha_translation_id' => 20,
            'is_active' => true,
            'priority' => 50,
        ]);

        app(AllohaPlayerSync::class)->sync($series, [
            ['id' => 20, 'name' => 'Coldfilm', 'iframe' => 'https://example.com/coldfilm'],
            ['id' => 30, 'name' => 'New Voice', 'iframe' => 'https://example.com/new'],
        ]);

        $players = PlayerSource::query()
            ->where('series_id', $series->id)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $players);
        $this->assertSame(20, (int) $players[0]->alloha_translation_id);
        $this->assertSame(50, (int) $players[0]->priority);
        $this->assertSame(30, (int) $players[1]->alloha_translation_id);
        $this->assertSame(40, (int) $players[1]->priority);
    }
}
