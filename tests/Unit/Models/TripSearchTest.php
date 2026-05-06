<?php

namespace Tests\Unit\Models;

use Carbon\Carbon;
use STS\Models\NodeGeo;
use STS\Models\TripSearch;
use STS\Models\User;
use Tests\TestCase;

class TripSearchTest extends TestCase
{
    private function makeNodeGeo(string $nameSuffix): NodeGeo
    {
        $node = new NodeGeo;
        $node->forceFill([
            'name' => 'Test node '.$nameSuffix,
            'lat' => -32.5,
            'lng' => -60.75,
            'type' => 'locality',
            'state' => 'Santa Fe',
            'country' => 'AR',
            'importance' => 1,
        ])->save();

        return $node->fresh();
    }

    public function test_belongs_to_user_origin_and_destination(): void
    {
        $user = User::factory()->create();
        $origin = $this->makeNodeGeo('origin');
        $destination = $this->makeNodeGeo('destination');

        $search = TripSearch::query()->create([
            'user_id' => $user->id,
            'origin_id' => $origin->id,
            'destination_id' => $destination->id,
            'search_date' => '2026-08-01 15:30:00',
            'amount_trips' => 3,
            'amount_trips_carpooleados' => 1,
            'client_platform' => TripSearch::PLATFORM_ANDROID,
            'is_passenger' => true,
            'results_json' => ['trip_ids' => [1, 2]],
        ]);

        $search = $search->fresh();
        $this->assertTrue($search->user->is($user));
        $this->assertTrue($search->origin->is($origin));
        $this->assertTrue($search->destination->is($destination));
    }

    public function test_search_date_results_json_and_scalar_casts(): void
    {
        $origin = $this->makeNodeGeo('o1');
        $destination = $this->makeNodeGeo('d1');

        $search = TripSearch::query()->create([
            'user_id' => null,
            'origin_id' => $origin->id,
            'destination_id' => $destination->id,
            'search_date' => '2026-01-20 09:00:00',
            'amount_trips' => 10,
            'amount_trips_carpooleados' => 0,
            'client_platform' => '2',
            'is_passenger' => 0,
            'results_json' => ['n' => 5, 'ok' => true],
        ]);

        $search = $search->fresh();
        $this->assertNull($search->user_id);
        $this->assertInstanceOf(Carbon::class, $search->search_date);
        $this->assertSame('2026-01-20 09:00:00', $search->search_date->format('Y-m-d H:i:s'));
        $this->assertSame(['n' => 5, 'ok' => true], $search->results_json);
        $this->assertIsInt($search->client_platform);
        $this->assertSame(2, $search->client_platform);
        $this->assertFalse($search->is_passenger);
    }

    public function test_platform_constants(): void
    {
        $this->assertSame(0, TripSearch::PLATFORM_WEB);
        $this->assertSame(1, TripSearch::PLATFORM_IOS);
        $this->assertSame(2, TripSearch::PLATFORM_ANDROID);
    }

    public function test_table_name_is_trip_searches(): void
    {
        $this->assertSame('trip_searches', (new TripSearch)->getTable());
    }
}
