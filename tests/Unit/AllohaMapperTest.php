<?php

namespace Tests\Unit;

use App\Services\AllohaMapper;
use PHPUnit\Framework\TestCase;

class AllohaMapperTest extends TestCase
{
    public function test_maps_runtime_and_premiere_from_alloha_payload(): void
    {
        $mapped = AllohaMapper::toSeriesAttributes([
            'data' => [
                'name' => 'Очень странные дела',
                'original_name' => 'Stranger Things',
                'alternative_name' => 'Загадочные события',
                'year' => 2016,
                'ids' => [
                    'kp' => 915196,
                    'imdb' => 'tt4574334',
                    'tmdb' => 66732,
                ],
                'token' => '83375a3b6fb415d5eca591ae712a2f',
                'category' => ['slug' => 'serial', 'name' => 'Сериал'],
                'premiere' => ['ru' => null, 'world' => '2016-07-15'],
                'rating' => ['age' => 16, 'kp' => 8.354, 'imdb' => 8.6],
                'runtime' => '00:51',
                'tagline' => 'The world is turning upside down.',
                'description' => 'Test description',
            ],
        ]);

        $this->assertSame('915196', $mapped['kp_id']);
        $this->assertSame('tt4574334', $mapped['imdb_id']);
        $this->assertSame('66732', $mapped['tmdb_id']);
        $this->assertSame(51, $mapped['duration_minutes']);
        $this->assertSame('2016-07-15', $mapped['premiere_date']);
        $this->assertSame('16', $mapped['age_limit']);
        $this->assertSame('series', $mapped['content_type']);
    }

    public function test_runtime_zero_is_not_mapped(): void
    {
        $mapped = AllohaMapper::toSeriesAttributes([
            'data' => [
                'name' => 'Test',
                'ids' => ['kp' => 1],
                'runtime' => '00:00',
            ],
        ]);

        $this->assertNull($mapped['duration_minutes']);
    }
}
