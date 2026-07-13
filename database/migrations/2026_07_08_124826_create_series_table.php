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
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->string('kp_id')->unique()->index();
            $table->string('slug')->unique()->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('poster_url')->nullable();
            $table->integer('year')->nullable();
            $table->decimal('kp_rating', 3, 1)->nullable();
            $table->decimal('imdb_rating', 3, 1)->nullable();
            $table->string('status')->nullable(); // e.g. continuing, ended, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};
