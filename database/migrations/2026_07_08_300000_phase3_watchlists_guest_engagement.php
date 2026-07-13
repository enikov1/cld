<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->string('guest_name', 120)->nullable()->after('user_id');
            $table->boolean('is_anonymous')->default(false)->after('guest_name');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('guest_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->string('voter_key', 64);
            $table->tinyInteger('value');
            $table->timestamps();

            $table->unique(['series_id', 'voter_key']);
        });

        Schema::create('watchlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 120);
            $table->boolean('is_system')->default(false);
            $table->string('system_key', 40)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
            $table->index(['user_id', 'sort_order']);
        });

        Schema::create('watchlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('watchlist_id')->constrained('watchlists')->cascadeOnDelete();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['watchlist_id', 'series_id']);
        });

        $this->migrateUserLists();
    }

    public function down(): void
    {
        Schema::dropIfExists('watchlist_items');
        Schema::dropIfExists('watchlists');
        Schema::dropIfExists('guest_votes');

        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->dropColumn(['guest_name', 'is_anonymous']);
        });

        Schema::create('user_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->string('type');
            $table->timestamps();

            $table->unique(['user_id', 'series_id']);
            $table->index(['user_id', 'type']);
        });
    }

    private function migrateUserLists(): void
    {
        if (!Schema::hasTable('user_lists')) {
            return;
        }

        $systemLabels = [
            'watching' => ['name' => 'Смотрю', 'sort' => 10],
            'will-watch' => ['name' => 'Буду смотреть', 'sort' => 20],
            'seen' => ['name' => 'Просмотрено', 'sort' => 30],
            'favourite' => ['name' => 'Избранное', 'sort' => 40],
            'abandoned' => ['name' => 'Брошено', 'sort' => 50],
        ];

        $watchlistIds = [];

        $rows = DB::table('user_lists')->orderBy('id')->get();
        $userIds = $rows->pluck('user_id')->unique();

        foreach ($userIds as $userId) {
            foreach ($systemLabels as $key => $meta) {
                $slug = $key;
                $id = DB::table('watchlists')->insertGetId([
                    'user_id' => $userId,
                    'name' => $meta['name'],
                    'slug' => $slug,
                    'is_system' => true,
                    'system_key' => $key,
                    'sort_order' => $meta['sort'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $watchlistIds[$userId . ':' . $key] = $id;
            }
        }

        foreach ($rows as $row) {
            $listId = $watchlistIds[$row->user_id . ':' . $row->type] ?? null;
            if (!$listId) {
                continue;
            }

            DB::table('watchlist_items')->insertOrIgnore([
                'watchlist_id' => $listId,
                'series_id' => $row->series_id,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ]);
        }

        Schema::dropIfExists('user_lists');
    }
};
