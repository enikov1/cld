<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicStorageCacheTest extends TestCase
{
    public function test_media_route_sends_year_long_cache_control_without_cookies(): void
    {
        Storage::disk('public')->put('posters/cache-probe.webp', 'fake-webp');

        try {
            $response = $this->get('/media/posters/cache-probe.webp');

            $response->assertOk();
            $cache = (string) $response->headers->get('Cache-Control');
            $this->assertStringContainsString('max-age=31536000', $cache);
            $this->assertStringContainsString('public', $cache);
            $this->assertNull($response->headers->get('Set-Cookie'));
        } finally {
            Storage::disk('public')->delete('posters/cache-probe.webp');
        }
    }

    public function test_media_route_rejects_path_traversal(): void
    {
        $this->get('/media/../.env')->assertNotFound();
        $this->get('/media/posters/../../.env')->assertNotFound();
        $this->get('/media/posters/missing.webp')->assertNotFound();
    }
}
