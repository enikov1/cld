<?php

namespace Tests\Unit;

use App\Models\Series;
use App\Services\TmdbMapper;
use Tests\TestCase;

class TmdbMapperTest extends TestCase
{
    public function test_maps_tv_details(): void
    {
        $mapped = TmdbMapper::toSeriesAttributes([
            'id' => 66732,
            'name' => 'Очень странные дела',
            'original_name' => 'Stranger Things',
            'overview' => 'Test overview',
            'first_air_date' => '2016-07-15',
            'last_air_date' => '2025-01-01',
            'status' => 'Returning Series',
            'popularity' => 512.45,
            'vote_average' => 8.6,
            'vote_count' => 12000,
            'episode_run_time' => [51],
            'number_of_episodes' => 42,
            'genres' => [['id' => 1, 'name' => 'Драма']],
            'origin_country' => ['US'],
            'poster_path' => '/abc.jpg',
            'external_ids' => ['imdb_id' => 'tt4574334'],
            'content_ratings' => [
                'results' => [
                    ['iso_3166_1' => 'US', 'rating' => 'TV-14'],
                    ['iso_3166_1' => 'RU', 'rating' => '16+'],
                ],
            ],
        ], true);

        $this->assertSame('66732', $mapped['tmdb_id']);
        $this->assertSame('tt4574334', $mapped['imdb_id']);
        $this->assertSame('Очень странные дела', $mapped['title']);
        $this->assertSame('Stranger Things', $mapped['title_original']);
        $this->assertSame('series', $mapped['content_type']);
        $this->assertSame(2016, $mapped['year']);
        $this->assertSame(2016, $mapped['start_year']);
        $this->assertSame(2025, $mapped['end_year']);
        $this->assertNull($mapped['finale_date']);
        $this->assertSame('2016-07-15', $mapped['premiere_date']);
        $this->assertSame(2142, $mapped['duration_minutes']);
        $this->assertSame('16', $mapped['age_limit']);
        $this->assertSame('ongoing', $mapped['broadcast_status']);
        $this->assertSame(['Драма'], $mapped['_genre_names']);
        $this->assertSame(['US'], $mapped['_country_names']);
        $this->assertStringContainsString('/abc.jpg', (string)$mapped['poster_source_url']);
    }

    public function test_maps_end_year_when_last_episode_year_is_later(): void
    {
        $mapped = TmdbMapper::toSeriesAttributes([
            'id' => 1396,
            'name' => 'Во все тяжкие',
            'first_air_date' => '2008-01-20',
            'last_air_date' => '2013-09-29',
            'status' => 'Ended',
            'episode_run_time' => [47],
            'number_of_episodes' => 62,
        ], true);

        $this->assertSame(2008, $mapped['year']);
        $this->assertSame(2008, $mapped['start_year']);
        $this->assertSame(2013, $mapped['end_year']);
        $this->assertSame('2008-01-20', $mapped['premiere_date']);
        $this->assertSame('2013-09-29', $mapped['finale_date']);
        $this->assertSame(2914, $mapped['duration_minutes']);
        $this->assertSame('completed', $mapped['broadcast_status']);
    }

    public function test_skips_end_year_when_last_episode_is_same_year(): void
    {
        $mapped = TmdbMapper::toSeriesAttributes([
            'id' => 99,
            'name' => 'Мини-сериал',
            'first_air_date' => '2024-03-01',
            'last_air_date' => '2024-11-20',
            'status' => 'Returning Series',
        ], true);

        $this->assertSame(2024, $mapped['year']);
        $this->assertSame(2024, $mapped['start_year']);
        $this->assertNull($mapped['end_year']);
        $this->assertNull($mapped['finale_date']);
    }

    public function test_maps_movie_details(): void
    {
        $mapped = TmdbMapper::toSeriesAttributes([
            'id' => 671,
            'title' => 'Гарри Поттер и философский камень',
            'original_title' => "Harry Potter and the Philosopher's Stone",
            'overview' => 'Movie overview',
            'release_date' => '2001-11-16',
            'runtime' => 152,
            'status' => 'Released',
            'popularity' => 100.1,
            'vote_average' => 7.9,
            'vote_count' => 9000,
            'genres' => [['id' => 2, 'name' => 'Фэнтези']],
            'production_countries' => [['name' => 'United Kingdom']],
            'tagline' => 'Let the magic begin.',
            'poster_path' => '/movie.jpg',
            'external_ids' => ['imdb_id' => 'tt0241527'],
            'release_dates' => [
                'results' => [[
                    'iso_3166_1' => 'US',
                    'release_dates' => [['certification' => 'PG', 'type' => 3]],
                ]],
            ],
        ], false);

        $this->assertSame('671', $mapped['tmdb_id']);
        $this->assertSame('film', $mapped['content_type']);
        $this->assertSame(2001, $mapped['year']);
        $this->assertSame(152, $mapped['duration_minutes']);
        $this->assertSame('12', $mapped['age_limit']);
        $this->assertSame('completed', $mapped['broadcast_status']);
        $this->assertSame('Let the magic begin.', $mapped['slogan']);
        $this->assertSame(['United Kingdom'], $mapped['_country_names']);
    }

    public function test_episode_air_stats_sum_runtimes_and_dates(): void
    {
        $stats = TmdbMapper::episodeAirStatsFromSeasonPayloads([
            0 => [
                'episodes' => [
                    ['episode_number' => 1, 'air_date' => '2007-12-01', 'runtime' => 90],
                ],
            ],
            1 => [
                'episodes' => [
                    ['episode_number' => 1, 'air_date' => '2008-01-20', 'runtime' => 58],
                    ['episode_number' => 2, 'air_date' => '2008-01-27', 'runtime' => null],
                ],
            ],
            5 => [
                'episodes' => [
                    ['episode_number' => 16, 'air_date' => '2013-09-29', 'runtime' => 55],
                ],
            ],
        ], 47);

        $this->assertSame('2008-01-20', $stats['first_air_date']);
        $this->assertSame('2013-09-29', $stats['last_air_date']);
        $this->assertSame(58 + 47 + 55, $stats['total_runtime']);
    }

    public function test_apply_air_metadata_sets_end_year_for_ongoing_later_season(): void
    {
        $series = new Series([
            'year' => 2010,
            'start_year' => 2010,
            'end_year' => null,
            'duration_minutes' => 43,
        ]);

        TmdbMapper::applyAirMetadata($series, [
            'id' => 1,
            'name' => 'Test',
            'first_air_date' => '2010-03-07',
            'last_air_date' => '2026-01-01',
            'status' => 'Returning Series',
            'episode_run_time' => [42],
            'number_of_episodes' => 10,
        ], true);

        $this->assertSame(2010, $series->year);
        $this->assertSame(2010, $series->start_year);
        $this->assertSame(2026, $series->end_year);
        $this->assertNull($series->finale_date);
        $premiere = $series->premiere_date;
        $this->assertSame(
            '2010-03-07',
            $premiere instanceof \DateTimeInterface ? $premiere->format('Y-m-d') : (string)$premiere,
        );
        $this->assertSame(420, $series->duration_minutes);
    }
}
