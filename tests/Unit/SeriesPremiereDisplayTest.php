<?php

namespace Tests\Unit;

use App\Models\Series;
use App\Services\KinoPoiskMapper;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class SeriesPremiereDisplayTest extends TestCase
{
    public function test_shows_day_month_and_year_when_premiere_date_is_known(): void
    {
        $series = new Series([
            'premiere_date' => Carbon::parse('2026-05-15'),
            'year' => 2026,
        ]);

        $this->assertFalse($series->premiereIsYearOnly());
        $this->assertSame('15 мая', $series->premiereDayMonthLabel());
        $this->assertSame('15 мая 2026', $series->premiereDateLabel());
    }

    public function test_treats_january_first_as_year_only_placeholder(): void
    {
        $series = new Series([
            'premiere_date' => Carbon::parse('1962-01-01'),
            'year' => 1962,
        ]);

        $this->assertTrue($series->premiereIsYearOnly());
        $this->assertNull($series->premiereDayMonthLabel());
        $this->assertSame('1962', $series->premiereDateLabel());
    }

    public function test_formats_age_limit_label(): void
    {
        $series = new Series(['age_limit' => 'age18']);

        $this->assertSame('18+', $series->ageLimitLabel());
        $this->assertSame('зрителям, достигшим 18+ лет', $series->ageLimitTooltip());
    }

    public function test_resolves_premiere_from_kinopoisk_distributions(): void
    {
        $date = KinoPoiskMapper::resolvePremiereDate([], [[
            'type' => 'PREMIERE',
            'date' => '1962-12-25',
        ]]);

        $this->assertSame('1962-12-25', $date);
    }
}
