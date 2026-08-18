<?php

namespace Tests\Unit;

use App\Support\SeriesSeoHtml;
use Tests\TestCase;

class SeriesSeoHtmlTest extends TestCase
{
    public function test_renders_spoiler_tags_inside_html(): void
    {
        $html = '<h2>Сюжет</h2><p>[spoiler]Герой погибает[/spoiler]</p>';

        $rendered = SeriesSeoHtml::render($html);

        $this->assertStringContainsString('comment-spoiler', $rendered);
        $this->assertStringContainsString('comment-spoiler__toggle', $rendered);
        $this->assertStringContainsString('Герой погибает', $rendered);
        $this->assertStringNotContainsString('[spoiler]', $rendered);
    }
}
