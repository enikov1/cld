<?php

namespace Tests\Unit;

use App\Support\PublicMedia;
use App\Support\TplRenderer;
use Tests\TestCase;

class PublicMediaTest extends TestCase
{
    public function test_url_rewrites_storage_image_paths(): void
    {
        $this->assertSame('/media/posters/kp-1.webp', PublicMedia::url('/storage/posters/kp-1.webp'));
        $this->assertStringStartsWith('/media/branding/logo.png', PublicMedia::url('/storage/branding/logo.png'));
        $this->assertSame('https://cdn.example/a.jpg', PublicMedia::url('https://cdn.example/a.jpg'));
        $this->assertSame('', PublicMedia::url(''));
    }

    public function test_rewrite_in_text_updates_html_and_json(): void
    {
        $html = '<img src="/storage/posters/kp-1.webp" alt="">';
        $this->assertSame('<img src="/media/posters/kp-1.webp" alt="">', PublicMedia::rewriteInText($html));

        $json = '{"image":"https://serialix.net/storage/branding/background.jpg"}';
        $this->assertStringStartsWith(
            '{"image":"https://serialix.net/media/branding/background.jpg',
            PublicMedia::rewriteInText($json)
        );
    }

    public function test_tpl_renderer_rewrites_poster_variables(): void
    {
        $dir = sys_get_temp_dir();
        $file = $dir . DIRECTORY_SEPARATOR . 'public-media-probe.tpl';
        file_put_contents($file, '<img src="{item.poster_url|raw}">');

        $renderer = new TplRenderer($dir);
        $html = $renderer->render('public-media-probe.tpl', [
            'item' => ['poster_url' => '/storage/posters/kp-9.webp'],
        ]);

        $this->assertSame('<img src="/media/posters/kp-9.webp">', $html);
        @unlink($file);
    }
}
