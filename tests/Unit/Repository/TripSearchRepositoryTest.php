<?php

namespace Tests\Unit\Repository;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use STS\Models\NodeGeo;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\TripSearch;
use STS\Models\User;
use STS\Repository\TripSearchRepository;
use Tests\TestCase;

class TripSearchRepositoryTest extends TestCase
{
    private function repo(): TripSearchRepository
    {
        return new TripSearchRepository;
    }

    private function makeNode(): NodeGeo
    {
        $node = new NodeGeo;
        $node->forceFill([
            'name' => 'N'.substr(uniqid('', true), 0, 8),
            'lat' => -34.0,
            'lng' => -58.0,
            'type' => 'city',
            'state' => 'BA',
            'country' => 'AR',
            'importance' => 1,
        ]);
        $node->save();

        return $node->fresh();
    }

    public function test_create_persists_row(): void
    {
        $user = User::factory()->create();
        $origin = $this->makeNode();
        $dest = $this->makeNode();

        $row = $this->repo()->create([
            'user_id' => $user->id,
            'origin_id' => $origin->id,
            'destination_id' => $dest->id,
            'search_date' => Carbon::parse('2024-06-01 12:00:00'),
            'amount_trips' => 3,
            'amount_trips_carpooleados' => 1,
            'client_platform' => TripSearch::PLATFORM_IOS,
            'is_passenger' => true,
            'results_json' => ['a' => 1],
        ]);

        $this->assertInstanceOf(TripSearch::class, $row);
        $this->assertDatabaseHas('trip_searches', [
            'id' => $row->id,
            'user_id' => $user->id,
            'origin_id' => $origin->id,
            'destination_id' => $dest->id,
            'amount_trips' => 3,
            'amount_trips_carpooleados' => 1,
            'client_platform' => TripSearch::PLATFORM_IOS,
        ]);
        $this->assertTrue($row->fresh()->is_passenger);
        $this->assertSame(['a' => 1], $row->fresh()->results_json);
    }

    public function test_track_search_uses_paginator_total_and_counts_carpooleados_on_current_items(): void
    {
        $user = User::factory()->create();
        $origin = $this->makeNode();
        $dest = $this->makeNode();

        $full = Trip::factory()->create(['total_seats' => 1, 'user_id' => $user->id]);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $full->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $open = Trip::factory()->create(['total_seats' => 4, 'user_id' => $user->id]);

        $items = collect([$full->fresh(), $open->fresh()]);
        $paginator = new LengthAwarePaginator($items, 42, 15, 1, ['path' => '/trips']);

        $row = $this->repo()->trackSearch(
            $user,
            $origin->id,
            $dest->id,
            $paginator,
            TripSearch::PLATFORM_ANDROID,
            '2024-12-01',
            true
        );

        $this->assertSame(42, $row->amount_trips);
        $this->assertSame(1, $row->amount_trips_carpooleados);
        $this->assertSame(TripSearch::PLATFORM_ANDROID, $row->client_platform);
        $this->assertTrue($row->is_passenger);
        $this->assertSame($user->id, $row->user_id);
        $this->assertTrue($row->search_date->isSameDay(Carbon::parse('2024-12-01')));
    }

    public function test_track_search_null_user_and_null_search_date(): void
    {
        $origin = $this->makeNode();
        $dest = $this->makeNode();
        $paginator = new LengthAwarePaginator(collect([]), 0, 15, 1, ['path' => '/']);

        $row = $this->repo()->trackSearch(null, $origin->id, $dest->id, $paginator);

        $this->assertNull($row->user_id);
        $this->assertSame(0, $row->amount_trips);
        $this->assertSame(0, $row->amount_trips_carpooleados);
        $this->assertNotNull($row->search_date);
        $this->assertTrue($row->search_date->isSameDay(Carbon::now()));
    }
}
