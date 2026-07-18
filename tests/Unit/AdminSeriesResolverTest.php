<?php

namespace Tests\Unit;

use App\Models\Series;
use App\Support\AdminSeriesResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSeriesResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_by_kp_id_before_primary_key(): void
    {
        $byPk = Series::query()->create([
            'kp_id' => '100',
            'slug' => 'by-pk',
            'title' => 'By PK',
            'is_active' => true,
        ]);

        $byKp = Series::query()->create([
            'kp_id' => (string) $byPk->id,
            'slug' => 'by-kp',
            'title' => 'By KP matching other PK',
            'is_active' => true,
        ]);

        $resolved = AdminSeriesResolver::byKey((string) $byPk->id);

        $this->assertSame($byKp->id, $resolved->id);
    }

    public function test_resolves_by_tmdb_id_before_primary_key(): void
    {
        $byPk = Series::query()->create([
            'kp_id' => 'kp-a',
            'slug' => 'pk-series',
            'title' => 'PK series',
            'is_active' => true,
        ]);

        $byTmdb = Series::query()->create([
            'kp_id' => 'kp-b',
            'tmdb_id' => (string) $byPk->id,
            'slug' => 'tmdb-series',
            'title' => 'TMDB series',
            'is_active' => true,
        ]);

        $resolved = AdminSeriesResolver::byKey((string) $byPk->id);

        $this->assertSame($byTmdb->id, $resolved->id);
    }

    public function test_soft_deleted_row_does_not_match_via_or_where(): void
    {
        $active = Series::query()->create([
            'kp_id' => 'kp-active',
            'tmdb_id' => 'tmdb-shared',
            'slug' => 'active',
            'title' => 'Active',
            'is_active' => true,
        ]);

        $trashed = Series::query()->create([
            'kp_id' => 'kp-trashed',
            'tmdb_id' => 'tmdb-other',
            'slug' => 'trashed',
            'title' => 'Trashed',
            'is_active' => true,
        ]);
        $trashed->delete();

        // Soft-deleted kp must not leak through ungrouped orWhere.
        $trashed->forceFill(['kp_id' => 'lookup-key'])->saveQuietly();

        $resolved = AdminSeriesResolver::byKey('tmdb-shared');
        $this->assertSame($active->id, $resolved->id);

        $this->expectException(ModelNotFoundException::class);
        AdminSeriesResolver::byKey('lookup-key');
    }

    public function test_with_trashed_can_find_deleted(): void
    {
        $series = Series::query()->create([
            'kp_id' => 'kp-del',
            'slug' => 'deleted',
            'title' => 'Deleted',
            'is_active' => true,
        ]);
        $series->delete();

        $resolved = AdminSeriesResolver::byKey('kp-del', true);
        $this->assertSame($series->id, $resolved->id);
    }
}
