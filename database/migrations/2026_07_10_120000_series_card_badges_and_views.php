<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series_view_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->date('view_date');
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();

            $table->unique(['series_id', 'view_date']);
            $table->index(['view_date', 'series_id']);
        });

        Schema::table('series', function (Blueprint $table) {
            $table->boolean('popular_badge_active')->default(false)->after('views_count');
            $table->timestamp('popular_badge_refreshed_at')->nullable()->after('popular_badge_active');
            $table->timestamp('last_episode_changed_at')->nullable()->after('last_episode_number');
        });

        Schema::table('notification_events', function (Blueprint $table) {
            $table->index(['series_id', 'event_type', 'created_at'], 'notification_events_series_type_created');
        });
    }

    public function down(): void
    {
        Schema::table('notification_events', function (Blueprint $table) {
            $table->dropIndex('notification_events_series_type_created');
        });

        Schema::table('series', function (Blueprint $table) {
            $table->dropColumn(['popular_badge_active', 'popular_badge_refreshed_at', 'last_episode_changed_at']);
        });

        Schema::dropIfExists('series_view_daily');
    }
};
