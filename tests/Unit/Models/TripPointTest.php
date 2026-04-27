<?php

namespace Tests\Unit\Models;

use STS\Models\Trip;
use STS\Models\TripPoint;
use STS\Models\User;
use Tests\TestCase;

class TripPointTest extends TestCase
{
    public function test_belongs_to_trip(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $point = TripPoint::factory()->create(['trip_id' => $trip->id]);

        $this->assertTrue($point->trip->is($trip));
    }

    public function test_json_address_casts_to_array(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $point = TripPoint::factory()->create([
            'trip_id' => $trip->id,
            'json_address' => ['city' => 'Rosario', 'street' => 'Mitre'],
        ]);

        $point = $point->fresh();
        $this->assertSame('Rosario', $point->json_address['city']);
        $this->assertSame('Mitre', $point->json_address['street']);
    }

    public function test_lat_lng_mutators_persist_trig_columns_on_create(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $lat = -12.5;
        $lng = 44.25;

        $point = TripPoint::factory()->create([
            'trip_id' => $trip->id,
            'lat' => $lat,
            'lng' => $lng,
        ]);
        $point = $point->fresh();
        $attrs = $point->getAttributes();

        $this->assertEqualsWithDelta(sin(deg2rad($lat)), (float) $attrs['sin_lat'], 1e-9);
        $this->assertEqualsWithDelta(cos(deg2rad($lat)), (float) $attrs['cos_lat'], 1e-9);
        $this->assertEqualsWithDelta(sin(deg2rad($lng)), (float) $attrs['sin_lng'], 1e-9);
        $this->assertEqualsWithDelta(cos(deg2rad($lng)), (float) $attrs['cos_lng'], 1e-9);
    }

    public function test_updating_lat_lng_recomputes_trig_columns(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $point = TripPoint::factory()->create([
            'trip_id' => $trip->id,
            'lat' => 1.0,
            'lng' => 2.0,
        ]);

        $point->lat = 30.0;
        $point->lng = 60.0;
        $point->save();
        $point = $point->fresh();
        $attrs = $point->getAttributes();

        $this->assertEqualsWithDelta(sin(deg2rad(30.0)), (float) $attrs['sin_lat'], 1e-9);
        $this->assertEqualsWithDelta(sin(deg2rad(60.0)), (float) $attrs['sin_lng'], 1e-9);
    }

    public function test_to_array_hides_timestamps_and_trig_columns(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $point = TripPoint::factory()->create(['trip_id' => $trip->id]);
        $array = $point->toArray();

        foreach (['created_at', 'updated_at', 'sin_lat', 'sin_lng', 'cos_lat', 'cos_lng'] as $key) {
            $this->assertArrayNotHasKey($key, $array, "Expected {$key} to be hidden from serialization");
        }
        $this->assertArrayHasKey('lat', $array);
        $this->assertArrayHasKey('lng', $array);
        $this->assertArrayHasKey('trip_id', $array);
    }

    public function test_table_name_is_trips_points(): void
    {
        $this->assertSame('trips_points', (new TripPoint)->getTable());
    }
}
