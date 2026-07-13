<?php

namespace Tests\Unit;

use App\Models\Series;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeriesPublishedScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_excludes_hidden_series(): void
    {
        $visible = Series::query()->create([
            'kp_id' => 'kp-visible',
            'slug' => 'visible-series',
            'title' => 'Visible series',
            'is_active' => true,
            'is_hidden' => false,
        ]);

        Series::query()->create([
            'kp_id' => 'kp-hidden',
            'slug' => 'hidden-series',
            'title' => 'Hidden series',
            'is_active' => true,
            'is_hidden' => true,
        ]);

        $publishedIds = Series::query()->published()->pluck('id')->all();

        $this->assertSame([$visible->id], $publishedIds);
    }
}
