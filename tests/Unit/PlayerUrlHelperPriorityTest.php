<?php

namespace Tests\Unit;

use App\Models\PlayerSource;
use App\Models\Series;
use App\Support\PlayerUrlHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerUrlHelperPriorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_player_tab_position_inserts_second_among_uneven_priorities(): void
    {
        $series = Series::query()->create([
            'kp_id' => '700001',
            'slug' => 'priority-test',
            'title' => 'Priority',
            'is_active' => true,
        ]);

        $first = PlayerSource::query()->create([
            'series_id' => $series->id,
            'provider' => 'First',
            'iframe_url' => 'https://example.com/1',
            'is_active' => true,
            'priority' => 100,
        ]);
        PlayerSource::query()->create([
            'series_id' => $series->id,
            'provider' => 'Also first',
            'iframe_url' => 'https://example.com/2',
            'is_active' => true,
            'priority' => 100,
        ]);
        $third = PlayerSource::query()->create([
            'series_id' => $series->id,
            'provider' => 'Third',
            'iframe_url' => 'https://example.com/3',
            'is_active' => true,
            'priority' => 1,
        ]);
        $target = PlayerSource::query()->create([
            'series_id' => $series->id,
            'provider' => 'Alloha',
            'iframe_url' => 'https://example.com/alloha',
            'is_active' => true,
            'priority' => 999,
        ]);

        PlayerUrlHelper::applyPlayerTabPosition($series, (int) $target->id, 2);

        $players = PlayerUrlHelper::activePlayersForSeries($series->fresh());
        $this->assertCount(4, $players);
        $this->assertSame('First', $players[0]['label']);
        $this->assertSame('Alloha', $players[1]['label']);
        $this->assertSame(40, PlayerSource::query()->find($target->id)?->priority);
        $this->assertSame(50, PlayerSource::query()->find($first->id)?->priority);
        $this->assertSame(10, PlayerSource::query()->find($third->id)?->priority);
    }

    public function test_apply_player_tab_position_puts_player_first(): void
    {
        $series = Series::query()->create([
            'kp_id' => '700002',
            'slug' => 'priority-first',
            'title' => 'Priority First',
            'is_active' => true,
        ]);

        PlayerSource::query()->create([
            'series_id' => $series->id,
            'provider' => 'Existing',
            'iframe_url' => 'https://example.com/1',
            'is_active' => true,
            'priority' => 80,
        ]);
        $target = PlayerSource::query()->create([
            'series_id' => $series->id,
            'provider' => 'Alloha',
            'iframe_url' => 'https://example.com/alloha',
            'is_active' => true,
            'priority' => 5,
        ]);

        PlayerUrlHelper::applyPlayerTabPosition($series, (int) $target->id, 1);

        $players = PlayerUrlHelper::activePlayersForSeries($series->fresh());
        $this->assertSame('Alloha', $players[0]['label']);
        $this->assertSame(20, $target->fresh()->priority);
    }
}
