<?php

namespace Tests\Unit;

use App\Support\AgeLimitFormatter;
use PHPUnit\Framework\TestCase;

class AgeLimitFormatterTest extends TestCase
{
    public function test_formats_kinopoisk_age_codes(): void
    {
        $this->assertSame('18+', AgeLimitFormatter::label('age18'));
        $this->assertSame('16+', AgeLimitFormatter::label('age16'));
    }

    public function test_formats_numeric_values(): void
    {
        $this->assertSame('18+', AgeLimitFormatter::label('18'));
        $this->assertSame('16+', AgeLimitFormatter::label('16+'));
    }

    public function test_normalizes_storage_value(): void
    {
        $this->assertSame('18', AgeLimitFormatter::normalize('age18'));
        $this->assertSame('16', AgeLimitFormatter::normalize('16+'));
    }

    public function test_builds_tooltip(): void
    {
        $this->assertSame(
            'зрителям, достигшим 18+ лет',
            AgeLimitFormatter::tooltip('age18')
        );
    }
}
