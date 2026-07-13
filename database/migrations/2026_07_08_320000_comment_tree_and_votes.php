<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('series_id')
                ->constrained('comments')
                ->cascadeOnDelete();
        });

        Schema::create('comment_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('voter_key', 64)->nullable();
            $table->tinyInteger('value');
            $table->timestamps();

            $table->unique(['comment_id', 'user_id']);
            $table->index(['comment_id', 'voter_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_votes');

        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
