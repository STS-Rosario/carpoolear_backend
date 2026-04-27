<?php

namespace Tests\Unit\Models;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use STS\Models\RouteCache;
use Tests\TestCase;

class RouteCacheTest extends TestCase
{
    /**
     * @return array{0: list<array{lat: float, lng: float}>, 1: string}
     */
    private function uniquePoints(): array
    {
        $token = uniqid('pt_', true);
        $points = [
            ['lat' => -32.0, 'lng' => -60.0],
            ['lat' => -31.5, 'lng' => -59.5, 'ref' => $token],
        ];

        return [$points, hash('sha256', json_encode($points))];
    }

    public function test_creating_sets_hashed_points_from_points_json(): void
    {
        [$points, $expectedHash] = $this->uniquePoints();

        $row = RouteCache::query()->create([
            'points' => $points,
            'route_data' => ['legs' => []],
            'expires_at' => null,
        ]);

        $row = $row->fresh();
        $this->assertSame($expectedHash, $row->hashed_points);
        $this->assertEquals($points, $row->points);
    }

    public function test_updating_points_recomputes_hashed_points(): void
    {
        [$pointsA] = $this->uniquePoints();
        $row = RouteCache::query()->create([
            'points' => $pointsA,
            'route_data' => ['v' => 1],
            'expires_at' => null,
        ]);

        $pointsB = [
            ['lat' => 10.0, 'lng' => 20.0],
            ['lat' => 10.1, 'lng' => 20.1, 'id' => uniqid('', true)],
        ];
        $row->points = $pointsB;
        $row->save();

        $row = $row->fresh();
        $this->assertSame(hash('sha256', json_encode($pointsB)), $row->hashed_points);
    }

    public function test_updating_route_data_without_changing_points_preserves_hash(): void
    {
        [$points, $expectedHash] = $this->uniquePoints();
        $row = RouteCache::query()->create([
            'points' => $points,
            'route_data' => ['v' => 1],
            'expires_at' => null,
        ]);

        $row->route_data = ['v' => 2, 'extra' => true];
        $row->save();

        $row = $row->fresh();
        $this->assertSame($expectedHash, $row->hashed_points);
    }

    public function test_route_data_and_expires_at_casts(): void
    {
        [$points] = $this->uniquePoints();

        $row = RouteCache::query()->create([
            'points' => $points,
            'route_data' => ['polyline' => 'abc', 'meters' => 5000],
            'expires_at' => '2026-10-01 12:00:00',
        ]);

        $row = $row->fresh();
        $this->assertEquals(['polyline' => 'abc', 'meters' => 5000], $row->route_data);
        $this->assertInstanceOf(Carbon::class, $row->expires_at);
        $this->assertSame('2026-10-01 12:00:00', $row->expires_at->format('Y-m-d H:i:s'));
    }

    public function test_duplicate_points_violate_unique_hashed_points(): void
    {
        [$points] = $this->uniquePoints();

        RouteCache::query()->create([
            'points' => $points,
            'route_data' => ['a' => 1],
            'expires_at' => null,
        ]);

        $this->expectException(QueryException::class);
        RouteCache::query()->create([
            'points' => $points,
            'route_data' => ['b' => 2],
            'expires_at' => null,
        ]);
    }

    public function test_table_name_is_route_cache(): void
    {
        $this->assertSame('route_cache', (new RouteCache)->getTable());
    }
}
