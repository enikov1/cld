<?php

use App\Models\SiteSetting;
use App\Support\SiteConfig;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (SiteConfig::definitions() as $key => $definition) {
            if (($definition['group'] ?? '') !== 'reviews') {
                continue;
            }

            if ($key === 'reviews_enabled') {
                continue;
            }

            $existing = SiteSetting::query()->where('key', $key)->value('value');
            if ($existing === null) {
                SiteSetting::set($key, $definition['default']);

                continue;
            }

            if ($existing !== '') {
                continue;
            }

            if (
                str_starts_with($key, 'reviews_ui_')
                || str_starts_with($key, 'reviews_msg_')
                || str_starts_with($key, 'reviews_label_')
            ) {
                SiteSetting::set($key, $definition['default']);
            }
        }
    }

    public function down(): void
    {
        // Keep saved settings on rollback.
    }
};
