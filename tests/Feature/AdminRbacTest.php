<?php

namespace Tests\Feature;

use App\Models\AdminToken;
use App\Support\AdminAccess;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['admin.token' => 'smoke-admin-token']);
        cache()->store('admin')->flush();
        AdminAccess::clearResolvedActor();
    }

    public function test_me_returns_master_role(): void
    {
        $this->withHeader('X-ADMIN-TOKEN', 'smoke-admin-token')
            ->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('role', 'full')
            ->assertJsonPath('actor_type', 'master')
            ->assertJsonPath('name', 'ADMIN_TOKEN')
            ->assertJsonPath('abilities.0', '*');
    }

    public function test_content_token_can_read_series_but_not_settings(): void
    {
        $plain = $this->createToken('Editor', abilities: AdminPermissions::presets()['content']);

        $this->withHeader('X-ADMIN-TOKEN', $plain)
            ->getJson('/api/admin/series')
            ->assertOk();

        $this->withHeader('X-ADMIN-TOKEN', $plain)
            ->getJson('/api/admin/settings')
            ->assertForbidden();

        $this->withHeader('X-ADMIN-TOKEN', $plain)
            ->getJson('/api/admin/comments')
            ->assertForbidden();
    }

    public function test_moderation_token_can_read_comments_but_not_series(): void
    {
        $plain = $this->createToken('Mod', abilities: AdminPermissions::presets()['moderation']);

        $this->withHeader('X-ADMIN-TOKEN', $plain)
            ->getJson('/api/admin/comments')
            ->assertOk();

        $this->withHeader('X-ADMIN-TOKEN', $plain)
            ->getJson('/api/admin/series')
            ->assertForbidden();

        $this->withHeader('X-ADMIN-TOKEN', $plain)
            ->postJson('/api/admin/backup/restore', [
                'name' => 'backup_2026-01-01_00-00-00.zip',
                'source' => 'local',
                'confirm_token' => 'smoke-admin-token',
            ])
            ->assertForbidden();
    }

    public function test_custom_abilities_token_only_gets_selected_sections(): void
    {
        $plain = $this->createToken('Series only', abilities: ['content.series', 'content.media']);

        $this->withHeader('X-ADMIN-TOKEN', $plain)
            ->getJson('/api/admin/series')
            ->assertOk();

        $this->withHeader('X-ADMIN-TOKEN', $plain)
            ->getJson('/api/admin/media')
            ->assertOk();

        $this->withHeader('X-ADMIN-TOKEN', $plain)
            ->getJson('/api/admin/collections')
            ->assertForbidden();

        $me = $this->withHeader('X-ADMIN-TOKEN', $plain)
            ->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('role', 'custom')
            ->json();

        $this->assertContains('series', $me['pages']);
        $this->assertContains('media', $me['pages']);
        $this->assertNotContains('collections', $me['pages']);
        $this->assertNotContains('settings', $me['pages']);
    }

    public function test_create_token_with_abilities_returns_plaintext_once(): void
    {
        $response = $this->withHeader('X-ADMIN-TOKEN', 'smoke-admin-token')
            ->postJson('/api/admin/admin-tokens', [
                'name' => 'Content bot',
                'abilities' => ['content.series', 'content.sync'],
            ]);

        $response->assertCreated()
            ->assertJsonPath('item.role', 'custom')
            ->assertJsonPath('item.abilities.0', 'content.series')
            ->assertJsonStructure(['token', 'item' => ['id', 'name', 'role', 'abilities']]);

        $plain = $response->json('token');
        $this->assertNotEmpty($plain);
        $this->assertDatabaseHas('admin_tokens', [
            'name' => 'Content bot',
            'token_hash' => AdminToken::hashToken($plain),
        ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'admin_token.create',
        ]);
    }

    public function test_create_token_with_role_preset_still_works(): void
    {
        $response = $this->withHeader('X-ADMIN-TOKEN', 'smoke-admin-token')
            ->postJson('/api/admin/admin-tokens', [
                'name' => 'Preset content',
                'role' => 'content',
            ]);

        $response->assertCreated()
            ->assertJsonPath('item.role', 'content');

        $abilities = $response->json('item.abilities');
        $this->assertIsArray($abilities);
        $this->assertContains('content.series', $abilities);
        $this->assertNotContains('*', $abilities);
    }

    public function test_update_token_abilities_and_name(): void
    {
        $plain = $this->createToken('Old', abilities: ['content.series']);
        $token = AdminToken::query()->where('token_hash', AdminToken::hashToken($plain))->firstOrFail();

        $this->withHeader('X-ADMIN-TOKEN', 'smoke-admin-token')
            ->putJson('/api/admin/admin-tokens/' . $token->id, [
                'name' => 'Renamed',
                'abilities' => ['moderation.comments', 'moderation.users'],
            ])
            ->assertOk()
            ->assertJsonPath('item.name', 'Renamed')
            ->assertJsonPath('item.role', 'custom');

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'admin_token.update',
        ]);

        // Live abilities apply without re-login (header auth).
        $this->withHeader('X-ADMIN-TOKEN', $plain)
            ->getJson('/api/admin/comments')
            ->assertOk();

        $this->withHeader('X-ADMIN-TOKEN', $plain)
            ->getJson('/api/admin/series')
            ->assertForbidden();
    }

    public function test_deactivate_token_blocks_access(): void
    {
        $plain = $this->createToken('Temp', abilities: ['content.series']);
        $token = AdminToken::query()->where('token_hash', AdminToken::hashToken($plain))->firstOrFail();

        $this->withHeader('X-ADMIN-TOKEN', $plain)
            ->getJson('/api/admin/series')
            ->assertOk();

        $this->withHeader('X-ADMIN-TOKEN', 'smoke-admin-token')
            ->putJson('/api/admin/admin-tokens/' . $token->id, [
                'is_active' => false,
            ])
            ->assertOk();

        $this->withHeader('X-ADMIN-TOKEN', $plain)
            ->getJson('/api/admin/series')
            ->assertUnauthorized();
    }

    public function test_regenerate_invalidates_old_secret(): void
    {
        $plain = $this->createToken('Regen', abilities: ['content.series']);
        $token = AdminToken::query()->where('token_hash', AdminToken::hashToken($plain))->firstOrFail();

        $response = $this->withHeader('X-ADMIN-TOKEN', 'smoke-admin-token')
            ->postJson('/api/admin/admin-tokens/' . $token->id . '/regenerate')
            ->assertOk();

        $newPlain = $response->json('token');
        $this->assertNotSame($plain, $newPlain);

        $this->withHeader('X-ADMIN-TOKEN', $plain)
            ->getJson('/api/admin/series')
            ->assertUnauthorized();

        $this->withHeader('X-ADMIN-TOKEN', $newPlain)
            ->getJson('/api/admin/series')
            ->assertOk();

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'admin_token.regenerate',
        ]);
    }

    public function test_cannot_delete_own_token(): void
    {
        $plain = $this->createToken('Self', abilities: ['admin.tokens', 'content.series']);
        $token = AdminToken::query()->where('token_hash', AdminToken::hashToken($plain))->firstOrFail();

        $this->withHeader('X-ADMIN-TOKEN', $plain)
            ->deleteJson('/api/admin/admin-tokens/' . $token->id)
            ->assertStatus(422);
    }

    public function test_delete_token(): void
    {
        $plain = $this->createToken('Doomed', abilities: ['content.series']);
        $token = AdminToken::query()->where('token_hash', AdminToken::hashToken($plain))->firstOrFail();

        $this->withHeader('X-ADMIN-TOKEN', 'smoke-admin-token')
            ->deleteJson('/api/admin/admin-tokens/' . $token->id)
            ->assertOk();

        $this->assertDatabaseMissing('admin_tokens', ['id' => $token->id]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'admin_token.delete',
        ]);
    }

    public function test_meta_returns_catalog_and_presets(): void
    {
        $this->withHeader('X-ADMIN-TOKEN', 'smoke-admin-token')
            ->getJson('/api/admin/admin-tokens/meta')
            ->assertOk()
            ->assertJsonStructure([
                'catalog' => [['key', 'label', 'group', 'pages']],
                'presets' => ['full', 'content', 'moderation', 'custom'],
                'roles',
            ]);
    }

    public function test_session_actor_picks_up_ability_changes_from_db(): void
    {
        $plain = $this->createToken('Editor', abilities: ['content.series']);
        $token = AdminToken::query()->where('token_hash', AdminToken::hashToken($plain))->firstOrFail();

        $login = \Illuminate\Http\Request::create('/api/admin/site-access', 'POST');
        $login->headers->set('X-ADMIN-TOKEN', $plain);
        $this->app->instance('request', $login);

        $cookie = AdminAccess::makeCookie($login);
        $this->assertNotNull($cookie);

        $before = \Illuminate\Http\Request::create('/api/admin/series', 'GET');
        $before->cookies->set(AdminAccess::COOKIE_NAME, $cookie->getValue());
        AdminAccess::clearResolvedActor();
        $this->assertTrue(AdminAccess::can('content.series', $before));
        $this->assertFalse(AdminAccess::can('content.collections', $before));

        $this->withHeader('X-ADMIN-TOKEN', 'smoke-admin-token')
            ->putJson('/api/admin/admin-tokens/' . $token->id, [
                'abilities' => ['content.collections'],
            ])
            ->assertOk();

        $after = \Illuminate\Http\Request::create('/api/admin/collections', 'GET');
        $after->cookies->set(AdminAccess::COOKIE_NAME, $cookie->getValue());
        AdminAccess::clearResolvedActor();

        $this->assertTrue(AdminAccess::can('content.collections', $after));
        $this->assertFalse(AdminAccess::can('content.series', $after));

        $meActor = AdminAccess::resolveActor($after);
        $this->assertSame('Editor', $meActor['name'] ?? null);
        $this->assertSame('custom', $meActor['role'] ?? null);
        $this->assertSame(['content.collections'], $meActor['abilities'] ?? null);
    }

    public function test_non_full_cannot_manage_tokens(): void
    {
        $plain = $this->createToken('NoTokens', abilities: ['content.series']);

        $this->withHeader('X-ADMIN-TOKEN', $plain)
            ->getJson('/api/admin/admin-tokens')
            ->assertForbidden();
    }

    /**
     * @param list<string> $abilities
     */
    private function createToken(string $name, array $abilities): string
    {
        $plain = AdminToken::generatePlaintext();
        AdminToken::query()->create([
            'name' => $name,
            'token_hash' => AdminToken::hashToken($plain),
            'role' => AdminPermissions::inferRole($abilities),
            'abilities' => AdminPermissions::normalizeAbilities($abilities),
            'is_active' => true,
        ]);

        return $plain;
    }
}
