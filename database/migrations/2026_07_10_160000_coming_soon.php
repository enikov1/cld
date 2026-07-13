<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->boolean('is_coming_soon')->default(false)->after('is_pinned');
            $table->unsignedInteger('anticipation_yes_count')->default(0)->after('is_coming_soon');
            $table->unsignedInteger('anticipation_no_count')->default(0)->after('anticipation_yes_count');
        });

        Schema::create('series_anticipation_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('voter_key', 64)->nullable();
            $table->tinyInteger('value');
            $table->timestamps();

            $table->unique(['series_id', 'user_id']);
            $table->unique(['series_id', 'voter_key']);
            $table->index(['series_id', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series_anticipation_votes');

        Schema::table('series', function (Blueprint $table) {
            $table->dropColumn(['is_coming_soon', 'anticipation_yes_count', 'anticipation_no_count']);
        });
    }
};
