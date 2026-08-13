<?php

namespace Tests\Unit;

use App\Services\AllohaAutoSyncSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllohaAutoSyncSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_include_bump_date_off(): void
    {
        $defaults = AllohaAutoSyncSettings::defaults();

        $this->assertFalse($defaults['bump_date_on_update']);
    }

    public function test_normalize_reads_bump_date_flag(): void
    {
        $normalized = AllohaAutoSyncSettings::normalize([
            'bump_date_on_update' => true,
        ]);

        $this->assertTrue($normalized['bump_date_on_update']);
    }

    public function test_to_import_flags_passes_bump_date_for_existing(): void
    {
        $flags = AllohaAutoSyncSettings::toImportFlags(
            AllohaAutoSyncSettings::normalize(['bump_date_on_update' => true]),
            false,
        );

        $this->assertTrue($flags['bump_date']);
    }

    public function test_to_import_flags_never_bumps_new_series(): void
    {
        $flags = AllohaAutoSyncSettings::toImportFlags(
            AllohaAutoSyncSettings::normalize(['bump_date_on_update' => true]),
            true,
        );

        $this->assertFalse($flags['bump_date']);
    }
}
