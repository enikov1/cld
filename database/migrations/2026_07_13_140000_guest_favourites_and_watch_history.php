<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_favourites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->string('guest_key', 64);
            $table->timestamps();

            $table->unique(['series_id', 'guest_key']);
            $table->index('guest_key');
        });

        Schema::create('watch_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('guest_key', 64)->nullable();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->timestamp('viewed_at');
            $table->timestamps();

            $table->unique(['user_id', 'series_id'], 'watch_history_user_series');
            $table->unique(['guest_key', 'series_id'], 'watch_history_guest_series');
            $table->index(['user_id', 'viewed_at']);
            $table->index(['guest_key', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_history');
        Schema::dropIfExists('guest_favourites');
    }
};
