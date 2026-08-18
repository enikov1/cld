<?php

namespace Tests\Unit;

use App\Models\Series;
use App\Services\KinoPoiskMapper;
use Carbon\Carbon;
use Tests\TestCase;

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

    public function test_premiere_countdown_for_coming_soon_series(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13'));

        $series = new Series([
            'premiere_date' => Carbon::parse('2026-08-16'),
            'year' => 2026,
            'is_coming_soon' => true,
        ]);

        $this->assertSame('через 3 дня', $series->premiereCountdownLabel());

        Carbon::setTestNow();
    }

    public function test_premiere_countdown_hidden_when_not_coming_soon(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13'));

        $series = new Series([
            'premiere_date' => Carbon::parse('2026-08-16'),
            'year' => 2026,
            'is_coming_soon' => false,
        ]);

        $this->assertNull($series->premiereCountdownLabel());

        Carbon::setTestNow();
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

    public function test_year_full_formats_start_end_range(): void
    {
        $range = new Series(['year' => 2010, 'start_year' => 2010, 'end_year' => 2014]);
        $this->assertSame(2010, $range->yearStart());
        $this->assertSame(2014, $range->yearEnd());
        $this->assertSame('2010-2014', $range->yearFull());

        $ongoing = new Series(['year' => 2010, 'start_year' => 2010, 'end_year' => null]);
        $this->assertSame('2010', $ongoing->yearFull());

        $stillAiring = new Series(['year' => 2024, 'start_year' => 2024, 'end_year' => 2026]);
        $this->assertSame('2024-2026', $stillAiring->yearFull());

        $sameYear = new Series(['year' => 2014, 'start_year' => 2014, 'end_year' => 2014]);
        $this->assertSame('2014', $sameYear->yearFull());
    }

    public function test_finale_date_label_uses_full_date_when_series_ended(): void
    {
        $series = new Series([
            'premiere_date' => Carbon::parse('2008-01-20'),
            'finale_date' => Carbon::parse('2013-09-29'),
            'start_year' => 2008,
            'end_year' => 2013,
        ]);

        $this->assertSame('29 сентября 2013', $series->finaleDateLabel());
    }

    public function test_duration_label_formats_days_hours_and_minutes(): void
    {
        $this->assertSame('1 день 23 часа', (new Series(['duration_minutes' => 2820]))->durationLabel());
        $this->assertSame('4 дня 9 часов 25 минут', (new Series(['duration_minutes' => 6325]))->durationLabel());
        $this->assertSame('45 минут', (new Series(['duration_minutes' => 45]))->durationLabel());
        $this->assertSame('2 часа 15 минут', (new Series(['duration_minutes' => 135]))->durationLabel());
        $this->assertNull((new Series(['duration_minutes' => null]))->durationLabel());
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
