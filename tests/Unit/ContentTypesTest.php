<?php

namespace Tests\Unit;

use App\Support\ContentTypes;
use PHPUnit\Framework\TestCase;

class ContentTypesTest extends TestCase
{
    public function test_slugs_and_labels(): void
    {
        $this->assertSame(
            ['film', 'series', 'cartoon', 'cartoon_series', 'anime', 'dorama', 'tv_show'],
            ContentTypes::slugs(),
        );
        $this->assertSame('Мультсериал', ContentTypes::label('cartoon_series'));
        $this->assertSame(4, ContentTypes::index('cartoon_series'));
        $this->assertSame('anime', ContentTypes::slugByIndex(5));
    }

    public function test_for_tpl_flags(): void
    {
        $flags = ContentTypes::forTpl('cartoon_series');

        $this->assertSame('', $flags['type-1']);
        $this->assertSame('', $flags['type-3']);
        $this->assertSame('1', $flags['type-4']);
        $this->assertSame('', $flags['type-5']);
    }

    public function test_serial_and_film_like_groups(): void
    {
        $this->assertTrue(ContentTypes::isFilmLike('film'));
        $this->assertTrue(ContentTypes::isFilmLike('cartoon'));
        $this->assertFalse(ContentTypes::isFilmLike('anime'));

        $this->assertTrue(ContentTypes::isSerialLike('series'));
        $this->assertTrue(ContentTypes::isSerialLike('anime'));
        $this->assertFalse(ContentTypes::isSerialLike('film'));
    }
}
