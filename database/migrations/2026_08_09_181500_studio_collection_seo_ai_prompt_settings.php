<?php

use App\Support\SiteConfig;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SiteConfig::ensureDefaults();
    }

    public function down(): void
    {
        // Keep settings — removing would wipe admin customizations.
    }
};
