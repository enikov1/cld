<?php

namespace Tests\Unit;

use App\Models\ReactionType;
use App\Models\Series;
use App\Models\SeriesReactionVote;
use App\Services\AdminReactionStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminReactionStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_aggregates_types_and_top_series(): void
    {
        Cache::flush();

        $hot = $this->makeSeries('Hot Show');
        $cold = $this->makeSeries('Cold Show');
        $fire = $this->makeType('🔥', 'Fire', 10);
        $love = $this->makeType('❤️', 'Love', 20);

        $this->vote($hot->id, $fire->id, 'a');
        $this->vote($hot->id, $fire->id, 'b');
        $this->vote($hot->id, $love->id, 'c');
        $this->vote($cold->id, $love->id, 'd');

        $report = AdminReactionStatsService::report('30d', 'day');

        $this->assertTrue($report['ready']);
        $this->assertSame(4, $report['summary']['votes_period']);
        $this->assertSame(2, $report['summary']['series_period']);
        $this->assertSame(4, $report['summary']['guests_period']);
        $this->assertSame($hot->id, $report['top_series'][0]['id']);
        $this->assertSame(3, $report['top_series'][0]['votes']);
        $this->assertSame('🔥', $report['top_series'][0]['top_emoji']);

        $byType = collect($report['by_type'])->keyBy('id');
        $this->assertSame(2, $byType[$fire->id]['votes']);
        $this->assertSame(2, $byType[$love->id]['votes']);
        $this->assertSame(1, $byType[$fire->id]['series_count']);
        $this->assertSame(2, $byType[$love->id]['series_count']);
    }

    public function test_period_filter_excludes_old_votes(): void
    {
        Cache::flush();

        $series = $this->makeSeries('Dated');
        $type = $this->makeType('👍', 'Like', 10);
        $this->vote($series->id, $type->id, 'fresh');
        $this->vote($series->id, $type->id, 'old', now()->subDays(40));

        $report = AdminReactionStatsService::report('7d', 'day');

        $this->assertSame(1, $report['summary']['votes_period']);
        $this->assertSame(2, $report['summary']['votes_total']);
    }

    public function test_type_filter_limits_top_series_but_keeps_type_breakdown(): void
    {
        Cache::flush();

        $alpha = $this->makeSeries('Alpha');
        $beta = $this->makeSeries('Beta');
        $fire = $this->makeType('🔥', 'Fire', 10);
        $love = $this->makeType('❤️', 'Love', 20);

        $this->vote($alpha->id, $fire->id, 'a');
        $this->vote($beta->id, $love->id, 'b');
        $this->vote($beta->id, $love->id, 'c');

        $report = AdminReactionStatsService::report('all', 'day', null, null, $fire->id);

        $this->assertSame(1, $report['summary']['votes_period']);
        $this->assertCount(1, $report['top_series']);
        $this->assertSame($alpha->id, $report['top_series'][0]['id']);

        $byType = collect($report['by_type'])->keyBy('id');
        $this->assertSame(1, $byType[$fire->id]['votes']);
        $this->assertSame(2, $byType[$love->id]['votes']);
        $this->assertTrue($byType[$fire->id]['highlighted']);
    }

    public function test_report_is_cached_until_fresh(): void
    {
        Cache::flush();

        $series = $this->makeSeries('Cached');
        $type = $this->makeType('😄', 'Fun', 10);
        $this->vote($series->id, $type->id, 'one');

        $first = AdminReactionStatsService::report('today', 'day');
        $this->vote($series->id, $type->id, 'two');
        $second = AdminReactionStatsService::report('today', 'day');
        $fresh = AdminReactionStatsService::report('today', 'day', null, null, null, 25, true);

        $this->assertSame(1, $first['summary']['votes_period']);
        $this->assertSame(1, $second['summary']['votes_period']);
        $this->assertSame(2, $fresh['summary']['votes_period']);
    }

    private function makeSeries(string $title): Series
    {
        return Series::query()->create([
            'kp_id' => 'kp-' . uniqid(),
            'slug' => 'series-' . uniqid(),
            'title' => $title,
            'is_active' => true,
            'is_hidden' => false,
            'views_count' => 0,
        ]);
    }

    private function makeType(string $emoji, string $label, int $sort): ReactionType
    {
        return ReactionType::query()->create([
            'emoji' => $emoji,
            'label' => $label,
            'sort_order' => $sort,
            'is_active' => true,
        ]);
    }

    private function vote(int $seriesId, int $typeId, string $key, mixed $createdAt = null): void
    {
        $vote = SeriesReactionVote::query()->create([
            'series_id' => $seriesId,
            'reaction_type_id' => $typeId,
            'voter_key' => hash('sha256', $key),
        ]);

        if ($createdAt) {
            $vote->created_at = $createdAt;
            $vote->updated_at = $createdAt;
            $vote->save();
        }
    }
}
