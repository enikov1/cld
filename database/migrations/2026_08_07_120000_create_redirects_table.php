<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('redirects')) {
            return;
        }

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path', 191);
            $table->string('to_type', 16)->default('url');
            $table->string('to_path', 191)->nullable();
            $table->foreignId('series_id')->nullable()->constrained('series')->nullOnDelete();
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->string('note', 500)->nullable();
            $table->unsignedBigInteger('hits_count')->default(0);
            $table->timestamps();

            $table->unique('from_path');
            $table->index(['is_active', 'from_path']);
            $table->index('series_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
