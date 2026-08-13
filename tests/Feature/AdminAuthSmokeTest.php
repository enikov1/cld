<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['admin.token' => 'smoke-admin-token']);
    }

    public function test_admin_stats_requires_token(): void
    {
        $this->getJson('/api/admin/stats')->assertUnauthorized();
    }

    public function test_admin_stats_accepts_valid_token_header(): void
    {
        $this->withHeader('X-ADMIN-TOKEN', 'smoke-admin-token')
            ->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonStructure(['series_total', 'collections', 'studios']);
    }

    public function test_site_access_sets_opaque_cookie(): void
    {
        $response = $this->withHeader('X-ADMIN-TOKEN', 'smoke-admin-token')
            ->postJson('/api/admin/site-access');

        $response->assertOk();
        $cookie = $response->getCookie('admin_site_access', false);
        $this->assertNotNull($cookie);
        $this->assertNotSame('smoke-admin-token', $cookie->getValue());

        $this->withUnencryptedCookie('admin_site_access', $cookie->getValue())
            ->getJson('/api/admin/stats')
            ->assertOk();
    }

    public function test_restore_requires_confirm_token(): void
    {
        $this->withHeader('X-ADMIN-TOKEN', 'smoke-admin-token')
            ->postJson('/api/admin/backup/restore', [
                'name' => 'missing.zip',
                'source' => 'local',
            ])
            ->assertStatus(422);

        $this->withHeader('X-ADMIN-TOKEN', 'smoke-admin-token')
            ->postJson('/api/admin/backup/restore', [
                'name' => 'missing.zip',
                'source' => 'local',
                'confirm_token' => 'wrong-token',
            ])
            ->assertForbidden();
    }

    public function test_settings_rejects_unknown_keys(): void
    {
        $this->withHeader('X-ADMIN-TOKEN', 'smoke-admin-token')
            ->postJson('/api/admin/settings', [
                'settings' => [
                    ['key' => 'evil_arbitrary_key', 'value' => '1'],
                    ['key' => 'ui_msg_generic_error', 'value' => 'Safe message'],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('site_settings', ['key' => 'evil_arbitrary_key']);
        $this->assertDatabaseHas('site_settings', [
            'key' => 'ui_msg_generic_error',
            'value' => 'Safe message',
        ]);
    }

    public function test_settings_saves_branding_and_home_text_keys(): void
    {
        $this->withHeader('X-ADMIN-TOKEN', 'smoke-admin-token')
            ->postJson('/api/admin/settings', [
                'settings' => [
                    ['key' => 'site_name', 'value' => 'Test Site'],
                    ['key' => 'site_tagline', 'value' => 'Test tagline'],
                    ['key' => 'footer_text', 'value' => 'Test footer'],
                    ['key' => 'home_heading', 'value' => 'Home heading'],
                    ['key' => 'home_lead', 'value' => 'Home lead'],
                    ['key' => 'home_seo_html', 'value' => '<p>SEO</p>'],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('site_settings', ['key' => 'site_name', 'value' => 'Test Site']);
        $this->assertDatabaseHas('site_settings', ['key' => 'site_tagline', 'value' => 'Test tagline']);
        $this->assertDatabaseHas('site_settings', ['key' => 'footer_text', 'value' => 'Test footer']);
        $this->assertDatabaseHas('site_settings', ['key' => 'home_heading', 'value' => 'Home heading']);
        $this->assertDatabaseHas('site_settings', ['key' => 'home_lead', 'value' => 'Home lead']);
        $this->assertDatabaseHas('site_settings', ['key' => 'home_seo_html', 'value' => '<p>SEO</p>']);
    }

    public function test_settings_save_keeps_admin_session_after_cache_flush(): void
    {
        $login = $this->withHeader('X-ADMIN-TOKEN', 'smoke-admin-token')
            ->postJson('/api/admin/site-access');

        $login->assertOk();
        $cookie = $login->getCookie('admin_site_access', false);
        $this->assertNotNull($cookie);

        $this->withUnencryptedCookie('admin_site_access', $cookie->getValue())
            ->postJson('/api/admin/settings', [
                'settings' => [
                    ['key' => 'ui_msg_generic_error', 'value' => 'Updated message'],
                ],
            ])
            ->assertOk();

        $this->withUnencryptedCookie('admin_site_access', $cookie->getValue())
            ->getJson('/api/admin/stats')
            ->assertOk();
    }

    public function test_reaction_stats_requires_token(): void
    {
        $this->getJson('/api/admin/reactions/stats')->assertUnauthorized();
    }

    public function test_reaction_stats_returns_cached_report(): void
    {
        $this->withHeader('X-ADMIN-TOKEN', 'smoke-admin-token')
            ->getJson('/api/admin/reactions/stats?period=7d')
            ->assertOk()
            ->assertJsonStructure([
                'ready',
                'period',
                'cache_ttl',
                'summary' => ['votes_period', 'series_period', 'voters_period'],
                'by_type',
                'timeseries',
                'top_series',
                'types',
            ]);
    }
}
