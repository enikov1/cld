<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_queries', function (Blueprint $table) {
            $table->id();
            $table->string('query_normalized', 120)->unique();
            $table->string('query', 120);
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedInteger('suggest_hits')->default(0);
            $table->unsignedInteger('full_hits')->default(0);
            $table->timestamp('last_searched_at')->useCurrent();
            $table->timestamps();

            $table->index(['hits', 'last_searched_at']);
            $table->index('last_searched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_queries');
    }
};
