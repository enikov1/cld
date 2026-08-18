<?php

namespace Tests\Unit;

use App\Models\Country;
use App\Models\Genre;
use App\Models\Series;
use App\Models\Voice;
use App\Services\SeriesSeoAiContextService;
use App\Services\TmdbClient;
use Mockery;
use Tests\TestCase;

class SeriesSeoAiContextServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_build_includes_core_series_data_without_tmdb(): void
    {
        $series = new Series([
            'id' => 42,
            'slug' => 'from',
            'title' => 'Извне',
            'title_original' => 'From',
            'description' => 'Город-ловушка с монстрами.',
            'short_description' => 'Мистический триллер.',
            'year' => 2022,
            'kp_rating' => 7.8,
            'imdb_rating' => 7.9,
            'content_type' => 'series',
            'broadcast_status' => 'ongoing',
        ]);
        $series->setRelation('genres', collect([Genre::make(['name' => 'Триллер'])]));
        $series->setRelation('countries', collect([Country::make(['name' => 'США'])]));
        $series->setRelation('actors', collect([(object) ['name' => 'Гарольд Перрино']]));
        $series->setRelation('directors', collect());
        $series->setRelation('voices', collect([Voice::make(['name' => 'LostFilm'])]));
        $series->setRelation('studio', null);
        $series->setRelation('studios', collect());
        $series->setRelation('collections', collect());

        $client = Mockery::mock(TmdbClient::class);
        $client->shouldReceive('isConfigured')->andReturnFalse();

        $service = new SeriesSeoAiContextService($client);
        $built = $service->build($series, 'https://example.test/42-from-2022.html');

        $this->assertStringContainsString('Название: Извне', $built['context']);
        $this->assertStringContainsString('=== ЖАНРЫ ===', $built['context']);
        $this->assertStringContainsString('- Триллер', $built['context']);
        $this->assertStringContainsString('=== СТРАНЫ ===', $built['context']);
        $this->assertStringContainsString('- США', $built['context']);
        $this->assertStringContainsString('=== АКТЁРЫ ===', $built['context']);
        $this->assertStringContainsString('Гарольд Перрино', $built['context']);
        $this->assertStringContainsString('=== ОЗВУЧКИ', $built['context']);
        $this->assertStringContainsString('LostFilm', $built['context']);
        $this->assertSame([], $built['warnings']);
    }
}
