<?php

namespace Tests\Unit;

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
            'genres' => [['id' => 1, 'name' => 'Драма']],
            'origin_country' => ['US'],
            'poster_path' => '/abc.jpg',
            'external_ids' => ['imdb_id' => 'tt4574334'],
        ], true);

        $this->assertSame('66732', $mapped['tmdb_id']);
        $this->assertSame('tt4574334', $mapped['imdb_id']);
        $this->assertSame('Очень странные дела', $mapped['title']);
        $this->assertSame('Stranger Things', $mapped['title_original']);
        $this->assertSame('series', $mapped['content_type']);
        $this->assertSame(2016, $mapped['year']);
        $this->assertSame(2016, $mapped['start_year']);
        $this->assertSame(2025, $mapped['end_year']);
        $this->assertSame(51, $mapped['duration_minutes']);
        $this->assertSame('ongoing', $mapped['broadcast_status']);
        $this->assertSame(['Драма'], $mapped['_genre_names']);
        $this->assertSame(['US'], $mapped['_country_names']);
        $this->assertStringContainsString('/abc.jpg', (string)$mapped['poster_source_url']);
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
        ], false);

        $this->assertSame('671', $mapped['tmdb_id']);
        $this->assertSame('film', $mapped['content_type']);
        $this->assertSame(2001, $mapped['year']);
        $this->assertSame(152, $mapped['duration_minutes']);
        $this->assertSame('completed', $mapped['broadcast_status']);
        $this->assertSame('Let the magic begin.', $mapped['slogan']);
        $this->assertSame(['United Kingdom'], $mapped['_country_names']);
    }
}
