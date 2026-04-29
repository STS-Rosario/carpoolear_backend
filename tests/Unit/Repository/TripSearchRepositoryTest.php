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

    public function test_track_search_falls_back_to_count_when_total_returns_null(): void
    {
        // Mutation intent: preserve `$trips->total() ?? $trips->count()` — `total()` is evaluated first; null total must fall through to count().
        $user = User::factory()->create();
        $origin = $this->makeNode();
        $dest = $this->makeNode();

        $items = collect([
            Trip::factory()->create(['total_seats' => 3, 'user_id' => $user->id])->fresh(),
            Trip::factory()->create(['total_seats' => 3, 'user_id' => $user->id])->fresh(),
        ]);

        $trips = new class($items)
        {
            public function __construct(private \Illuminate\Support\Collection $items) {}

            public function total(): ?int
            {
                return null;
            }

            public function count(): int
            {
                return $this->items->count();
            }

            public function filter(callable $callback): \Illuminate\Support\Collection
            {
                return $this->items->filter($callback);
            }
        };

        $row = $this->repo()->trackSearch($user, $origin->id, $dest->id, $trips);

        $this->assertSame(2, $row->amount_trips);
        $this->assertSame(0, $row->amount_trips_carpooleados);
    }

    public function test_track_search_skips_carpooleado_scan_when_current_page_has_no_items(): void
    {
        // Mutation intent: `$trips->count()` is current-page item count on LengthAwarePaginator (~25–29), distinct from `total()` (~20).
        // Empty page with positive total must not run the filter callback branch (would wrongly increment carpooleados if tied to total).
        $user = User::factory()->create();
        $origin = $this->makeNode();
        $dest = $this->makeNode();

        $paginator = new LengthAwarePaginator(collect([]), 5, 15, 2, ['path' => '/trips']);

        $row = $this->repo()->trackSearch($user, $origin->id, $dest->id, $paginator);

        $this->assertSame(5, $row->amount_trips);
        $this->assertSame(0, $row->amount_trips_carpooleados);
    }

    public function test_track_search_counts_each_full_trip_as_carpooleado(): void
    {
        // Mutation intent: preserve `$trip->seats_available <= 0` filter over all items when `$trips->count() > 0`.
        $user = User::factory()->create();
        $origin = $this->makeNode();
        $dest = $this->makeNode();

        $fullOne = Trip::factory()->create(['total_seats' => 1, 'user_id' => $user->id]);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $fullOne->id,
            'user_id' => User::factory()->create()->id,
        ]);
        $fullTwo = Trip::factory()->create(['total_seats' => 1, 'user_id' => $user->id]);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $fullTwo->id,
            'user_id' => User::factory()->create()->id,
        ]);

        $items = collect([$fullOne->fresh(), $fullTwo->fresh()]);
        $paginator = new LengthAwarePaginator($items, 2, 15, 1, ['path' => '/trips']);

        $row = $this->repo()->trackSearch($user, $origin->id, $dest->id, $paginator);

        $this->assertSame(2, $row->amount_trips_carpooleados);
    }

    public function test_track_search_persists_search_payload_columns_for_array_remove_mutants(): void
    {
        // Mutation intent: guard `searchData` keys (`user_id`, `origin_id`, `destination_id`, `results_json`, …) — dropping a RemoveArrayItem mutant should fail DB or assertions.
        $user = User::factory()->create();
        $origin = $this->makeNode();
        $dest = $this->makeNode();
        $paginator = new LengthAwarePaginator(collect([]), 0, 15, 1, ['path' => '/']);

        $row = $this->repo()->trackSearch(
            $user,
            $origin->id,
            $dest->id,
            $paginator,
            TripSearch::PLATFORM_WEB,
            '2025-03-15 08:00:00',
            false
        );

        $this->assertDatabaseHas('trip_searches', [
            'id' => $row->id,
            'user_id' => $user->id,
            'origin_id' => $origin->id,
            'destination_id' => $dest->id,
            'amount_trips' => 0,
            'amount_trips_carpooleados' => 0,
            'client_platform' => TripSearch::PLATFORM_WEB,
            'is_passenger' => 0,
        ]);
        $this->assertTrue($row->search_date->eq(Carbon::parse('2025-03-15 08:00:00')));
        $this->assertSame([], $row->fresh()->results_json);
        $this->assertFalse($row->is_passenger);
    }
}
