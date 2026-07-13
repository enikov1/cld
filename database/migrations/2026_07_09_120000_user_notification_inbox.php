<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_via_email')->default(true)->after('remember_token');
            $table->boolean('notify_via_site')->default(true)->after('notify_via_email');
        });

        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->after('sent_at');
            $table->timestamp('dismissed_at')->nullable()->after('read_at');
        });

        Schema::table('notification_settings', function (Blueprint $table) {
            $table->boolean('is_enabled')->default(true)->after('notify_any');
        });

        Schema::table('series', function (Blueprint $table) {
            $table->date('premiere_date')->nullable()->after('year');
            $table->string('translation', 200)->nullable()->after('premiere_date');
            $table->string('channel_name', 120)->nullable()->after('translation');
            $table->string('channel_url')->nullable()->after('channel_name');
            $table->string('channel_logo_url')->nullable()->after('channel_url');
        });
    }

    public function down(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->dropColumn([
                'premiere_date',
                'translation',
                'channel_name',
                'channel_url',
                'channel_logo_url',
            ]);
        });

        Schema::table('notification_settings', function (Blueprint $table) {
            $table->dropColumn('is_enabled');
        });

        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->dropColumn(['read_at', 'dismissed_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_via_email', 'notify_via_site']);
        });
    }
};
