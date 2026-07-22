<?php

namespace Tests\Unit;

use App\Services\AllohaMapper;
use PHPUnit\Framework\TestCase;

class AllohaMapperContentTypeTest extends TestCase
{
    /**
     * @dataProvider categoryProvider
     */
    public function test_resolve_content_type_from_category_slug(string $slug, string $expected): void
    {
        $mapped = AllohaMapper::toSeriesAttributes([
            'data' => [
                'name' => 'Test title',
                'ids' => ['kp' => 123],
                'category' => ['slug' => $slug],
            ],
        ]);

        $this->assertSame($expected, $mapped['content_type']);
    }

    public static function categoryProvider(): array
    {
        return [
            ['serial', 'series'],
            ['anime-serial', 'anime'],
            ['tv-show', 'tv_show'],
            ['dorama', 'dorama'],
            ['multfilm', 'cartoon'],
            ['multserial', 'cartoon_series'],
        ];
    }
}
