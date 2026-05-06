<?php

namespace Tests\Unit\Models;

use Database\Factories\TripPointFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use STS\Models\Trip;
use STS\Models\TripPoint;
use STS\Models\User;
use Tests\TestCase;

class TripPointTest extends TestCase
{
    public function test_new_factory_returns_trip_point_factory(): void
    {
        // Mutation intent: `AlwaysReturnNull` on `newFactory()` (~14).
        $method = new \ReflectionMethod(TripPoint::class, 'newFactory');
        $method->setAccessible(true);
        $factory = $method->invoke(null);

        $this->assertInstanceOf(TripPointFactory::class, $factory);
        $this->assertInstanceOf(TripPointFactory::class, TripPoint::factory());
    }

    public function test_fillable_lists_point_payload_columns(): void
    {
        // Mutation intent: `RemoveArrayItem` on `getFillable()` (was uncovered on `$fillable` ~18–19).
        $expected = [
            'address',
            'json_address',
            'lat',
            'lng',
            'sin_lat',
            'sin_lng',
            'cos_lat',
            'cos_lng',
            'trip_id',
        ];

        $this->assertSame($expected, (new TripPoint)->getFillable());
    }

    public function test_hidden_lists_serialization_suppressed_attributes(): void
    {
        // Mutation intent: `RemoveArrayItem` on `getHidden()` (~22–23).
        $expected = [
            'created_at',
            'updated_at',
            'sin_lat',
            'sin_lng',
            'cos_lat',
            'cos_lng',
        ];

        $this->assertSame($expected, (new TripPoint)->getHidden());
    }

    public function test_casts_include_json_address_and_is_passenger_flag(): void
    {
        // Mutation intent: `RemoveArrayItem` on `casts()` (~28–30).
        $casts = (new TripPoint)->getCasts();

        $this->assertSame('array', $casts['json_address']);
        $this->assertSame('boolean', $casts['is_passenger']);
    }

    public function test_is_passenger_cast_coerces_to_boolean_without_persisting(): void
    {
        // Mutation intent: `RemoveArrayItem` / type drift on `is_passenger` cast (~30).
        $point = new TripPoint;
        $point->forceFill([
            'trip_id' => 1,
            'address' => 'x',
            'json_address' => [],
            'lat' => 0.0,
            'lng' => 0.0,
            'sin_lat' => 0.0,
            'sin_lng' => 0.0,
            'cos_lat' => 1.0,
            'cos_lng' => 1.0,
            'is_passenger' => 1,
        ]);

        $this->assertSame(true, $point->is_passenger);
    }

    public function test_trip_relation_returns_belongs_to(): void
    {
        // Mutation intent: `AlwaysReturnNull` on `trip()` (~50).
        $this->assertInstanceOf(BelongsTo::class, (new TripPoint)->trip());
    }

    public function test_mass_assignment_persists_all_fillable_columns(): void
    {
        // Mutation intent: every `getFillable()` key must remain mass-assignable.
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $lat = -11.5;
        $lng = 33.25;
        $payload = [
            'trip_id' => $trip->id,
            'address' => 'Mass-assign street 9',
            'json_address' => ['city' => 'Tandil'],
            'lat' => $lat,
            'lng' => $lng,
            'sin_lat' => sin(deg2rad($lat)),
            'sin_lng' => sin(deg2rad($lng)),
            'cos_lat' => cos(deg2rad($lat)),
            'cos_lng' => cos(deg2rad($lng)),
        ];

        $this->assertEqualsCanonicalizing(
            (new TripPoint)->getFillable(),
            array_keys($payload),
            'Payload must exercise every fillable key exactly once.'
        );

        $point = TripPoint::create($payload);
        $row = $point->fresh();

        $this->assertSame($trip->id, (int) $row->trip_id);
        $this->assertSame('Mass-assign street 9', $row->address);
        $this->assertSame('Tandil', $row->json_address['city']);
        $this->assertEqualsWithDelta($lat, (float) $row->lat, 1e-9);
        $this->assertEqualsWithDelta($lng, (float) $row->lng, 1e-9);
        $attrs = $row->getAttributes();
        $this->assertEqualsWithDelta(sin(deg2rad($lat)), (float) $attrs['sin_lat'], 1e-9);
        $this->assertEqualsWithDelta(sin(deg2rad($lng)), (float) $attrs['sin_lng'], 1e-9);
        $this->assertEqualsWithDelta(cos(deg2rad($lat)), (float) $attrs['cos_lat'], 1e-9);
        $this->assertEqualsWithDelta(cos(deg2rad($lng)), (float) $attrs['cos_lng'], 1e-9);
    }

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
