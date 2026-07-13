<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->string('broadcast_status')->nullable()->after('imdb_rating');
        });

        if (Schema::hasColumn('series', 'status')) {
            DB::table('series')->whereNotNull('status')->update([
                'broadcast_status' => DB::raw("CASE WHEN status = 'continuing' THEN 'ongoing' WHEN status IN ('ended','completed') THEN 'completed' ELSE status END"),
            ]);
            Schema::table('series', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }

        Schema::table('series', function (Blueprint $table) {
            $table->string('imdb_id')->nullable()->after('kp_id');
            $table->string('title_en')->nullable()->after('title');
            $table->string('title_original')->nullable()->after('title_en');
            $table->text('short_description')->nullable()->after('description');
            $table->string('slogan')->nullable()->after('short_description');
            $table->string('content_type', 32)->nullable()->after('slogan');
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('year');
            $table->unsignedSmallInteger('start_year')->nullable()->after('duration_minutes');
            $table->unsignedSmallInteger('end_year')->nullable()->after('start_year');
            $table->json('countries')->nullable()->after('end_year');
            $table->json('genres')->nullable()->after('countries');
            $table->string('age_limit', 32)->nullable()->after('genres');
            $table->string('kp_web_url')->nullable()->after('age_limit');
            $table->unsignedInteger('kp_votes_count')->nullable()->after('kp_rating');
            $table->unsignedInteger('imdb_votes_count')->nullable()->after('imdb_rating');
            $table->boolean('is_pinned')->default(false)->after('is_active');
            $table->timestamp('pinned_at')->nullable()->after('is_pinned');
            $table->integer('sort_order')->default(0)->after('pinned_at');
            $table->softDeletes();
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
            $table->text('meta_description')->nullable()->after('description');
            $table->integer('sort_order')->default(0)->after('cover_url');
            $table->boolean('is_pinned')->default(false)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropColumn(['description', 'meta_description', 'sort_order', 'is_pinned']);
        });

        Schema::table('series', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'imdb_id', 'title_en', 'title_original', 'short_description', 'slogan',
                'content_type', 'duration_minutes', 'start_year', 'end_year',
                'countries', 'genres', 'age_limit', 'kp_web_url',
                'kp_votes_count', 'imdb_votes_count', 'is_pinned', 'pinned_at', 'sort_order',
                'broadcast_status',
            ]);
            $table->string('status')->nullable();
        });
    }
};
