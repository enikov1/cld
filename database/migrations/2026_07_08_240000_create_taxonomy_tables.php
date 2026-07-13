<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('photo_url')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('series_genre', function (Blueprint $table) {
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->foreignId('genre_id')->constrained('genres')->cascadeOnDelete();
            $table->primary(['series_id', 'genre_id']);
        });

        Schema::create('series_country', function (Blueprint $table) {
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->primary(['series_id', 'country_id']);
        });

        Schema::create('series_person', function (Blueprint $table) {
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('role', 32)->default('actor');
            $table->primary(['series_id', 'person_id', 'role']);
        });

        $this->migrateJsonTaxonomies();

        if (Schema::hasColumn('series', 'genres')) {
            Schema::table('series', function (Blueprint $table) {
                $table->dropColumn(['genres', 'countries']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->json('countries')->nullable();
            $table->json('genres')->nullable();
        });

        Schema::dropIfExists('series_person');
        Schema::dropIfExists('series_country');
        Schema::dropIfExists('series_genre');
        Schema::dropIfExists('people');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('genres');
    }

    private function migrateJsonTaxonomies(): void
    {
        if (!Schema::hasColumn('series', 'genres')) {
            return;
        }

        $rows = DB::table('series')->select('id', 'genres', 'countries')->get();

        foreach ($rows as $row) {
            $genreNames = json_decode($row->genres ?? '[]', true) ?: [];
            $countryNames = json_decode($row->countries ?? '[]', true) ?: [];

            $genreIds = [];
            foreach ($genreNames as $name) {
                $name = trim((string)$name);
                if ($name === '') {
                    continue;
                }
                $slug = Str::slug($name) ?: 'genre-' . substr(md5($name), 0, 8);
                $id = DB::table('genres')->where('slug', $slug)->value('id');
                if (!$id) {
                    $id = DB::table('genres')->insertGetId([
                        'slug' => $this->uniqueSlug('genres', $slug),
                        'name' => $name,
                        'sort_order' => 0,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $genreIds[] = $id;
            }

            $countryIds = [];
            foreach ($countryNames as $name) {
                $name = trim((string)$name);
                if ($name === '') {
                    continue;
                }
                $slug = Str::slug($name) ?: 'country-' . substr(md5($name), 0, 8);
                $id = DB::table('countries')->where('slug', $slug)->value('id');
                if (!$id) {
                    $id = DB::table('countries')->insertGetId([
                        'slug' => $this->uniqueSlug('countries', $slug),
                        'name' => $name,
                        'sort_order' => 0,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $countryIds[] = $id;
            }

            foreach (array_unique($genreIds) as $gid) {
                DB::table('series_genre')->insertOrIgnore([
                    'series_id' => $row->id,
                    'genre_id' => $gid,
                ]);
            }

            foreach (array_unique($countryIds) as $cid) {
                DB::table('series_country')->insertOrIgnore([
                    'series_id' => $row->id,
                    'country_id' => $cid,
                ]);
            }
        }
    }

    private function uniqueSlug(string $table, string $base): string
    {
        $slug = $base;
        $n = 2;
        while (DB::table($table)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $n;
            $n++;
        }

        return $slug;
    }
};
