<?php

namespace Tests\Unit;

use App\Support\TplGuide;
use PHPUnit\Framework\TestCase;

class TplGuideTest extends TestCase
{
    public function test_payload_has_articles_nav_and_search_index(): void
    {
        $payload = TplGuide::payload();

        $this->assertSame('TPL-DOC', $payload['title']);
        $this->assertNotEmpty($payload['articles']);
        $this->assertNotEmpty($payload['nav']);
        $this->assertCount(count($payload['articles']), $payload['search_index']);

        $ids = array_column($payload['articles'], 'id');
        $this->assertContains('intro', $ids);
        $this->assertContains('porting', $ids);
        $this->assertContains('ref-layout', $ids);
        $this->assertContains('ref-series', $ids);
    }

    public function test_download_html_is_self_contained(): void
    {
        $html = TplGuide::downloadHtml();

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('id="docs-data"', $html);
        $this->assertStringContainsString('TPL-DOC', $html);
        $this->assertStringContainsString('Поиск по тегам', $html);
    }
}
