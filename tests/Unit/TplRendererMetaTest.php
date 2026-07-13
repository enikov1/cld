<?php

namespace Tests\Unit;

use App\Support\TplRenderer;
use PHPUnit\Framework\TestCase;

class TplRendererMetaTest extends TestCase
{
    public function test_extract_meta_directives_strips_blocks_from_body(): void
    {
        $renderer = new TplRenderer(__DIR__ . '/../../resources/tpl/default');

        $tpl = <<<'TPL'
[meta-title]{series.title} — онлайн[/meta-title]
[meta-description]{series.description}[/meta-description]
<div>{series.title}</div>
TPL;

        $result = $renderer->extractMetaDirectives($tpl);

        $this->assertSame('<div>{series.title}</div>', $result['body']);
        $this->assertSame('{series.title} — онлайн', $result['directives']['title']);
        $this->assertSame('{series.description}', $result['directives']['description']);
    }

    public function test_render_page_resolves_meta_and_body(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tpl-meta-' . uniqid('', true);
        mkdir($dir);

        file_put_contents($dir . DIRECTORY_SEPARATOR . 'page.tpl', <<<'TPL'
[meta-title]{item.title}[/meta-title]
[meta-canonical]https://example.test/{item.slug|raw}[/meta-canonical]
<p>{item.title}</p>
TPL);

        $renderer = new TplRenderer($dir);
        $page = $renderer->renderPage('page.tpl', [
            'item' => ['title' => 'Игра престолов', 'slug' => 'igra-prestolov'],
        ]);

        @unlink($dir . DIRECTORY_SEPARATOR . 'page.tpl');
        @rmdir($dir);

        $this->assertSame('<p>Игра престолов</p>', $page['content']);
        $this->assertSame('Игра престолов', $page['meta']['title']);
        $this->assertSame('https://example.test/igra-prestolov', $page['meta']['canonical']);
    }
}
