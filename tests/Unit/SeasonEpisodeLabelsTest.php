<?php

namespace Tests\Unit;

use App\Support\SeasonEpisodeLabels;
use PHPUnit\Framework\TestCase;

class SeasonEpisodeLabelsTest extends TestCase
{
    public function test_season_type_formats(): void
    {
        $this->assertSame('3 сезон', SeasonEpisodeLabels::formatSeason(3, 1));
        $this->assertSame('1, 2, 3 сезон', SeasonEpisodeLabels::formatSeason(3, 2));
        $this->assertSame('1-3 сезон', SeasonEpisodeLabels::formatSeason(3, 3));
        $this->assertSame('1-2 сезон', SeasonEpisodeLabels::formatSeason(2, 4));
        $this->assertSame('1-4, 5 сезон', SeasonEpisodeLabels::formatSeason(5, 4));
        $this->assertSame('1 по 5 сезон', SeasonEpisodeLabels::formatSeason(5, 5));
    }

    public function test_episode_type_formats(): void
    {
        $this->assertSame('12 серия', SeasonEpisodeLabels::formatEpisode(12, 1));
        $this->assertSame('1-12 серия', SeasonEpisodeLabels::formatEpisode(12, 3));
    }
}
