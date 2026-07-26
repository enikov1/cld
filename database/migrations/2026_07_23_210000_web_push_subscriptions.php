<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_via_push')->default(true)->after('notify_via_site');
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint', 500);
            $table->string('public_key', 255)->nullable();
            $table->string('auth_token', 255)->nullable();
            $table->string('content_encoding', 32)->default('aesgcm');
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'endpoint']);
            $table->index('endpoint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notify_via_push');
        });
    }
};
