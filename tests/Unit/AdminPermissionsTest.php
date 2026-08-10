<?php

namespace Tests\Unit;

use App\Support\AdminPermissions;
use Tests\TestCase;

class AdminPermissionsTest extends TestCase
{
    public function test_normalize_full_collapses_to_star(): void
    {
        $this->assertSame(['*'], AdminPermissions::normalizeAbilities(['*', 'content.series']));
    }

    public function test_infer_role_from_presets(): void
    {
        $this->assertSame('full', AdminPermissions::inferRole(['*']));
        $this->assertSame('content', AdminPermissions::inferRole(AdminPermissions::presets()['content']));
        $this->assertSame('moderation', AdminPermissions::inferRole(AdminPermissions::presets()['moderation']));
        $this->assertSame('custom', AdminPermissions::inferRole(['content.series']));
    }

    public function test_abilities_can_checks_exact_and_star(): void
    {
        $this->assertTrue(AdminPermissions::abilitiesCan(['*'], 'admin.settings'));
        $this->assertTrue(AdminPermissions::abilitiesCan(['content.series'], 'content.series'));
        $this->assertFalse(AdminPermissions::abilitiesCan(['content.series'], 'admin.settings'));
    }

    public function test_page_keys_for_custom_actor(): void
    {
        $pages = AdminPermissions::pageKeysForActor([
            'role' => 'custom',
            'abilities' => ['content.series', 'moderation.comments'],
        ]);

        $this->assertContains('dashboard', $pages);
        $this->assertContains('series', $pages);
        $this->assertContains('comments', $pages);
        $this->assertNotContains('settings', $pages);
        $this->assertNotContains('admin-access', $pages);
    }

    public function test_legacy_role_can_still_works(): void
    {
        $this->assertTrue(AdminPermissions::roleCan('content', 'content.series'));
        $this->assertFalse(AdminPermissions::roleCan('content', 'admin.tokens'));
        $this->assertTrue(AdminPermissions::roleCan('full', 'admin.tokens'));
    }

    public function test_catalog_keys_are_unique(): void
    {
        $keys = AdminPermissions::allAbilityKeys();
        $this->assertSame(count($keys), count(array_unique($keys)));
        $this->assertNotEmpty($keys);
    }
}
