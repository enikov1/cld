<?php

namespace Tests\Unit;

use App\Models\ReactionType;
use App\Models\Series;
use App\Models\SeriesReactionVote;
use App\Models\SiteSetting;
use App\Services\ReactionWidgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReactionWidgetServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_emoji_uses_highest_count_and_sort_order_tiebreak(): void
    {
        SiteSetting::query()->updateOrCreate(['key' => 'reactions_enabled'], ['value' => '1']);

        $series = Series::query()->create([
            'kp_id' => 'kp-reactions',
            'slug' => 'reactions-series',
            'title' => 'Reactions Series',
            'is_active' => true,
            'is_hidden' => false,
        ]);

        $fire = ReactionType::query()->create(['emoji' => '🔥', 'label' => 'Fire', 'sort_order' => 2, 'is_active' => true]);
        $love = ReactionType::query()->create(['emoji' => '❤️', 'label' => 'Love', 'sort_order' => 1, 'is_active' => true]);

        SeriesReactionVote::query()->create([
            'series_id' => $series->id,
            'reaction_type_id' => $fire->id,
            'voter_key' => hash('sha256', 'a'),
        ]);
        SeriesReactionVote::query()->create([
            'series_id' => $series->id,
            'reaction_type_id' => $love->id,
            'voter_key' => hash('sha256', 'b'),
        ]);
        SeriesReactionVote::query()->create([
            'series_id' => $series->id,
            'reaction_type_id' => $love->id,
            'voter_key' => hash('sha256', 'c'),
        ]);

        $top = ReactionWidgetService::topEmojisForSeriesIds([$series->id]);

        $this->assertSame('❤️', $top[$series->id]);
    }
}
