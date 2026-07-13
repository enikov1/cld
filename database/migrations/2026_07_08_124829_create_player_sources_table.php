<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('player_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->string('provider')->index(); // LostFilm / HDrezka Studio / etc
            $table->text('iframe_url'); // URL провайдера (вкладка в плеере)
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0); // higher = show first
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_sources');
    }
};
