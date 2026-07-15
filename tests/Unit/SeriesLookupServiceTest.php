<?php

namespace Tests\Unit;

use App\Services\SeriesLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SeriesLookupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_normalizes_kinopoisk_and_tmdb_results(): void
    {
        config([
            'kinopoisk.api_key' => 'kp-test',
            'tmdb.api_key' => 'tmdb-test',
        ]);

        Http::fake([
            '*/v2.1/films/search-by-keyword*' => Http::response([
                'films' => [[
                    'filmId' => 688,
                    'nameRu' => 'Гарри Поттер и философский камень',
                    'nameEn' => 'Harry Potter',
                    'year' => 2001,
                    'posterUrlPreview' => 'https://kp.test/poster.jpg',
                    'genres' => [['genre' => 'фэнтези']],
                    'type' => 'FILM',
                ]],
            ]),
            '*/search/multi*' => Http::response([
                'results' => [
                    [
                        'id' => 671,
                        'media_type' => 'movie',
                        'title' => 'Harry Potter',
                        'release_date' => '2001-11-16',
                        'genre_ids' => [14],
                        'poster_path' => '/abc.jpg',
                        'vote_average' => 7.9,
                    ],
                    [
                        'id' => 999,
                        'media_type' => 'person',
                        'name' => 'Actor',
                    ],
                ],
            ]),
            '*/genre/movie/list*' => Http::response([
                'genres' => [['id' => 14, 'name' => 'Фэнтези']],
            ]),
            '*/genre/tv/list*' => Http::response([
                'genres' => [],
            ]),
        ]);

        $result = app(SeriesLookupService::class)->search('гарри', 5);

        $this->assertCount(2, $result['results']);
        $this->assertSame('kinopoisk', $result['results'][0]['source']);
        $this->assertSame('688', $result['results'][0]['id']);
        $this->assertSame('film', $result['results'][0]['media_type']);
        $this->assertSame('tmdb', $result['results'][1]['source']);
        $this->assertSame('671', $result['results'][1]['id']);
        $this->assertSame(['Фэнтези'], $result['results'][1]['genres']);
    }

    public function test_search_returns_warning_for_short_query(): void
    {
        $result = app(SeriesLookupService::class)->search('a', 5);

        $this->assertSame([], $result['results']);
        $this->assertNotEmpty($result['warnings']);
    }
}
