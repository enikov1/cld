<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1..10
            $table->text('body');
            $table->string('status')->default('pending'); // pending|approved|rejected
            $table->string('author_name', 120)->nullable();
            $table->boolean('is_editorial')->default(false);
            $table->timestamps();

            $table->index(['series_id', 'status', 'created_at']);
            $table->index(['series_id', 'status', 'rating']);
            $table->unique(['series_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
