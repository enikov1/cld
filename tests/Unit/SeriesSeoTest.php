<?php

namespace Tests\Unit;

use App\Models\Genre;
use App\Models\Person;
use App\Models\Series;
use App\Support\SeriesSeo;
use App\Support\Speedbar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeriesSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_description_adds_query_and_truncates(): void
    {
        $series = new Series([
            'title' => 'Менталист',
            'short_description' => str_repeat('Очень длинный сюжет про Патрика Джейна. ', 20),
        ]);
        $renderer = new \App\Support\TplRenderer(sys_get_temp_dir());

        $description = SeriesSeo::metaDescription($series, '', $renderer, []);

        $this->assertStringStartsWith('Менталист смотреть онлайн.', $description);
        $this->assertTrue(mb_strlen($description) <= SeriesSeo::SNIPPET_MAX + 1);
        $this->assertStringEndsWith('…', $description);
    }

    public function test_one_line_collapses_title_newlines(): void
    {
        $this->assertSame(
            'Менталист смотреть онлайн в хорошем HD качестве бесплатно',
            SeriesSeo::oneLine("Менталист смотреть онлайн в хорошем\nHD качестве бесплатно")
        );
    }

    public function test_snippet_cuts_on_word_boundary(): void
    {
        $text = 'Менталист смотреть онлайн. ' . str_repeat('слово ', 40);
        $snippet = SeriesSeo::snippet($text, 80);

        $this->assertTrue(mb_strlen($snippet) <= 81);
        $this->assertStringEndsWith('…', $snippet);
        $this->assertStringNotContainsString('  ', $snippet);
    }

    public function test_absolute_url_keeps_http_and_prefixes_paths(): void
    {
        $this->assertSame('https://cdn.example/poster.jpg', SeriesSeo::absoluteUrl('https://cdn.example/poster.jpg'));
        $this->assertSame(url('/storage/posters/kp-1.webp'), SeriesSeo::absoluteUrl('/storage/posters/kp-1.webp'));
    }

    public function test_schema_and_og_types_depend_on_content_type(): void
    {
        $series = new Series(['content_type' => 'series']);
        $film = new Series(['content_type' => 'film']);

        $this->assertSame('TVSeries', SeriesSeo::schemaType($series));
        $this->assertSame('video.tv_show', SeriesSeo::ogType($series));
        $this->assertSame('Movie', SeriesSeo::schemaType($film));
        $this->assertSame('video.movie', SeriesSeo::ogType($film));
    }

    public function test_aggregate_rating_omits_thin_vote_counts(): void
    {
        $series = Series::query()->create([
            'kp_id' => 'kp-' . uniqid(),
            'slug' => 'thin-rating-' . uniqid(),
            'title' => 'Thin',
            'is_active' => true,
            'is_hidden' => false,
            'kp_rating' => 10,
            'kp_votes_count' => 1,
        ]);

        $this->assertNull(SeriesSeo::aggregateRating($series));
    }

    public function test_aggregate_rating_uses_kinopoisk_when_enough_votes(): void
    {
        $series = Series::query()->create([
            'kp_id' => 'kp-' . uniqid(),
            'slug' => 'kp-rating-' . uniqid(),
            'title' => 'Rated',
            'is_active' => true,
            'is_hidden' => false,
            'kp_rating' => 8.1,
            'kp_votes_count' => 1200,
        ]);

        $rating = SeriesSeo::aggregateRating($series);

        $this->assertSame('AggregateRating', $rating['@type']);
        $this->assertSame('8.1', $rating['ratingValue']);
        $this->assertSame(1200, $rating['ratingCount']);
    }

    public function test_json_ld_includes_people_genres_and_video_object(): void
    {
        $series = Series::query()->create([
            'kp_id' => 'kp-' . uniqid(),
            'slug' => 'mentalist-' . uniqid(),
            'title' => 'Менталист',
            'title_original' => 'The Mentalist',
            'content_type' => 'series',
            'year' => 2008,
            'description' => 'Описание сериала для схемы.',
            'poster_url' => '/storage/posters/kp-412344.webp',
            'duration_minutes' => 45,
            'is_active' => true,
            'is_hidden' => false,
            'kp_rating' => 8.1,
            'kp_votes_count' => 900,
        ]);

        $genre = Genre::query()->create([
            'slug' => 'detektiv-' . uniqid(),
            'name' => 'детектив',
            'is_active' => true,
            'is_hidden' => false,
        ]);
        $actor = Person::query()->create([
            'slug' => 'simon-baker-' . uniqid(),
            'name' => 'саймон бейкер',
            'is_active' => true,
            'is_hidden' => false,
        ]);
        $series->genres()->attach($genre->id);
        $series->actors()->attach($actor->id, ['role' => 'actor']);
        $series->load(['genres', 'actors', 'directors', 'countries']);

        $nodes = SeriesSeo::jsonLdNodes($series, 'https://lordserial.net/104-mentalist-2008.html', [
            [
                'season_number' => 1,
                'episodes' => [['episode_number' => 1], ['episode_number' => 2]],
            ],
        ]);

        $this->assertSame('TVSeries', $nodes[0]['@type']);
        $this->assertSame('The Mentalist', $nodes[0]['alternateName']);
        $this->assertSame(['Детектив'], $nodes[0]['genre']);
        $this->assertSame('Person', $nodes[0]['actor'][0]['@type']);
        $this->assertSame(1, $nodes[0]['numberOfSeasons']);
        $this->assertSame(2, $nodes[0]['numberOfEpisodes']);
        $this->assertStringContainsString('/storage/posters/kp-412344.webp', $nodes[0]['image']);
        $this->assertStringStartsWith('http', $nodes[0]['image']);
        $this->assertSame('VideoObject', $nodes[1]['@type']);
        $this->assertSame('WatchAction', $nodes[1]['potentialAction']['@type']);
    }

    public function test_speedbar_includes_genre_crumb(): void
    {
        $series = new Series([
            'id' => 104,
            'slug' => 'mentalist',
            'title' => 'Менталист',
            'year' => 2008,
        ]);
        $genre = new Genre([
            'slug' => 'detektiv',
            'name' => 'детектив',
        ]);
        $series->setRelation('genres', collect([$genre]));

        $labels = array_column(Speedbar::forSeries($series)->items(), 'label');

        $this->assertSame(['Главная', 'Детектив', 'Менталист'], $labels);
    }
}
