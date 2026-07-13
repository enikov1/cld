<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->unsignedSmallInteger('season_number');
            $table->string('title')->nullable();
            $table->timestamps();

            $table->unique(['series_id', 'season_number']);
        });

        Schema::create('episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('seasons')->cascadeOnDelete();
            $table->unsignedSmallInteger('episode_number');
            $table->string('title')->nullable();
            $table->timestamp('release_at')->nullable();
            $table->string('status')->default('scheduled'); // released|scheduled
            $table->string('voice', 120)->nullable();
            $table->timestamps();

            $table->unique(['season_id', 'episode_number']);
            $table->index(['season_id', 'status']);
        });

        Schema::create('notification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->foreignId('episode_id')->nullable()->constrained('episodes')->nullOnDelete();
            $table->unsignedSmallInteger('season_number')->nullable();
            $table->unsignedSmallInteger('episode_number')->nullable();
            $table->string('voice', 120)->nullable();
            $table->string('event_type')->default('new_episode');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_event_id')->constrained('notification_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued'); // queued|sent|failed
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['notification_event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_events');
        Schema::dropIfExists('episodes');
        Schema::dropIfExists('seasons');
    }
};
