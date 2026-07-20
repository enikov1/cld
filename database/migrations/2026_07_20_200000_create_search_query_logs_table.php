<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_query_logs', function (Blueprint $table) {
            $table->id();
            $table->string('query', 120);
            $table->string('query_normalized', 120);
            $table->string('source', 16)->default('full');
            $table->boolean('found')->default(false);
            $table->unsignedInteger('results_count')->default(0);
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at', 'found']);
            $table->index(['query_normalized', 'created_at']);
            $table->index(['source', 'created_at']);
            $table->index('ip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_query_logs');
    }
};
