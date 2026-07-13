<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->text('body');
            $table->string('status')->default('approved'); // pending|approved|rejected
            $table->timestamps();

            $table->index(['series_id', 'status', 'created_at']);
        });

        Schema::create('user_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->tinyInteger('value'); // 1 or -1
            $table->timestamps();

            $table->unique(['user_id', 'series_id']);
        });

        Schema::create('user_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->string('type'); // watching|will-watch|seen|favourite|abandoned
            $table->timestamps();

            $table->unique(['user_id', 'series_id']);
            $table->index(['user_id', 'type']);
        });

        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->json('voices')->nullable();
            $table->boolean('notify_any')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'series_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
        Schema::dropIfExists('user_lists');
        Schema::dropIfExists('user_votes');
        Schema::dropIfExists('comments');
    }
};
