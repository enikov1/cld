<?php

namespace Tests\Unit;

use App\Services\TmdbBroadcastStatusMapper;
use PHPUnit\Framework\TestCase;

class TmdbBroadcastStatusMapperTest extends TestCase
{
    public function test_maps_tv_statuses(): void
    {
        $this->assertSame('ongoing', TmdbBroadcastStatusMapper::fromDetails(['status' => 'Returning Series'], 'series'));
        $this->assertSame('ongoing', TmdbBroadcastStatusMapper::fromDetails(['status' => 'In Production'], 'series'));
        $this->assertSame('ongoing', TmdbBroadcastStatusMapper::fromDetails(['status' => 'Planned'], 'series'));
        $this->assertSame('ongoing', TmdbBroadcastStatusMapper::fromDetails(['status' => 'Pilot'], 'series'));
        $this->assertSame('completed', TmdbBroadcastStatusMapper::fromDetails(['status' => 'Ended'], 'series'));
        $this->assertSame('completed', TmdbBroadcastStatusMapper::fromDetails(['status' => 'Canceled'], 'series'));
        $this->assertNull(TmdbBroadcastStatusMapper::fromDetails(['status' => ''], 'series'));
    }

    public function test_maps_movie_statuses(): void
    {
        $this->assertSame('completed', TmdbBroadcastStatusMapper::fromDetails(['status' => 'Released'], 'film'));
        $this->assertSame('completed', TmdbBroadcastStatusMapper::fromDetails(['status' => 'Canceled'], 'film'));
        $this->assertNull(TmdbBroadcastStatusMapper::fromDetails(['status' => 'In Production'], 'film'));
    }

    public function test_resolve_preserves_manual_paused_while_ongoing(): void
    {
        $this->assertSame('paused', TmdbBroadcastStatusMapper::resolve('paused', 'ongoing'));
        $this->assertSame('completed', TmdbBroadcastStatusMapper::resolve('paused', 'completed'));
        $this->assertSame('ongoing', TmdbBroadcastStatusMapper::resolve('completed', 'ongoing'));
        $this->assertSame('paused', TmdbBroadcastStatusMapper::resolve('paused', null));
    }
}
