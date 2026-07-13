<?php

namespace Tests\Unit;

use App\Models\GuestVote;
use App\Models\Series;
use App\Models\User;
use App\Models\UserVote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeriesUserRatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_rating_is_null_without_votes(): void
    {
        $series = $this->makeSeries();

        $this->assertNull($series->userRating());
        $this->assertNull($series->userRatingLabel());
    }

    public function test_user_rating_is_calculated_from_likes_and_dislikes(): void
    {
        $series = $this->makeSeries();
        $user = User::factory()->create();

        UserVote::query()->create([
            'user_id' => $user->id,
            'series_id' => $series->id,
            'value' => 1,
        ]);

        GuestVote::query()->create([
            'series_id' => $series->id,
            'voter_key' => 'guest-1',
            'value' => 1,
        ]);

        GuestVote::query()->create([
            'series_id' => $series->id,
            'voter_key' => 'guest-2',
            'value' => -1,
        ]);

        $this->assertSame(6.7, $series->userRating());
        $this->assertSame('6.7', $series->userRatingLabel());
    }

    private function makeSeries(): Series
    {
        return Series::query()->create([
            'kp_id' => 'kp-' . uniqid(),
            'slug' => 'test-series-' . uniqid(),
            'title' => 'Test Series',
            'is_active' => true,
            'is_hidden' => false,
        ]);
    }
}
