<?php

namespace Tests\Unit;

use App\Support\PaginationHelper;
use PHPUnit\Framework\TestCase;

class PaginationHelperTest extends TestCase
{
    public function test_robots_meta_is_empty_for_first_page(): void
    {
        $this->assertSame('', PaginationHelper::robotsMeta(1));
    }

    public function test_robots_meta_is_noindex_for_paginated_pages(): void
    {
        $this->assertSame('noindex,follow', PaginationHelper::robotsMeta(2));
        $this->assertSame('noindex,follow', PaginationHelper::robotsMeta(10));
    }

    public function test_robots_meta_respects_entity_noindex_on_first_page(): void
    {
        $this->assertSame('noindex,follow', PaginationHelper::robotsMeta(1, true));
    }

    public function test_is_paginated_request_detects_route_page(): void
    {
        $this->assertFalse(PaginationHelper::isPaginatedRequest(1));
        $this->assertTrue(PaginationHelper::isPaginatedRequest(2));
    }

    public function test_is_paginated_request_detects_path_segment(): void
    {
        $this->assertTrue(PaginationHelper::isPaginatedRequest(1, '/genre/fantastika/page/2/'));
        $this->assertFalse(PaginationHelper::isPaginatedRequest(1, '/genre/fantastika/page/1/'));
        $this->assertFalse(PaginationHelper::isPaginatedRequest(1, '/genre/fantastika/'));
    }
}
