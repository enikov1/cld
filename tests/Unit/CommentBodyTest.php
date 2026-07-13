<?php

namespace Tests\Unit;

use App\Support\CommentBody;
use PHPUnit\Framework\TestCase;

class CommentBodyTest extends TestCase
{
    public function test_normalize_strips_html_and_normalizes_spoiler_tags(): void
    {
        $this->assertSame(
            "Привет\n[SPOILER]секрет[/spoiler]",
            CommentBody::normalize("  <b>Привет</b>\r\n[SPOILER]секрет[/spoiler]  ")
        );
    }

    public function test_contains_link_detects_common_patterns(): void
    {
        $this->assertTrue(CommentBody::containsLink('Смотрите https://example.com'));
        $this->assertTrue(CommentBody::containsLink('Заходите на www.example.com'));
        $this->assertTrue(CommentBody::containsLink('Пишите mailto:test@example.com'));
        $this->assertTrue(CommentBody::containsLink('Ссылка example.ru/page'));
        $this->assertTrue(CommentBody::containsLink('[url=https://test.com]клик[/url]'));
    }

    public function test_contains_link_allows_plain_text(): void
    {
        $this->assertFalse(CommentBody::containsLink('Отличный сериал, жду продолжения!'));
        $this->assertFalse(CommentBody::containsLink('[spoiler]главный герой погибает[/spoiler]'));
    }

    public function test_render_html_escapes_text_and_builds_spoiler_markup(): void
    {
        $html = CommentBody::renderHtml('Текст <script> и [spoiler]сюрприз &amp; финал[/spoiler]');

        $this->assertStringContainsString('Текст &lt;script&gt; и ', $html);
        $this->assertStringContainsString('class="comment-spoiler"', $html);
        $this->assertStringContainsString('сюрприз &amp;amp; финал', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_effective_body_ignores_spoiler_markup(): void
    {
        $this->assertSame('секрет', CommentBody::effectiveBody('[spoiler]секрет[/spoiler]'));
        $this->assertSame('Привет мир', CommentBody::effectiveBody('Привет [spoiler]мир[/spoiler]'));
        $this->assertSame('', CommentBody::effectiveBody('[spoiler][/spoiler]'));
        $this->assertSame('', CommentBody::effectiveBody('[spoiler]   [/spoiler]'));
        $this->assertLessThan(2, mb_strlen(CommentBody::effectiveBody('[spoiler][/spoiler]')));
    }
}
