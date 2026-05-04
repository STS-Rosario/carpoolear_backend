<?php

namespace Tests\Unit\Models;

use Carbon\Carbon;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use STS\Models\Subscription;
use STS\Models\User;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    public function test_new_factory_returns_subscription_factory(): void
    {
        // Mutation intent: `AlwaysReturnNull` on `newFactory()` (~14).
        $method = new \ReflectionMethod(Subscription::class, 'newFactory');
        $method->setAccessible(true);
        $factory = $method->invoke(null);

        $this->assertInstanceOf(SubscriptionFactory::class, $factory);
        $this->assertInstanceOf(SubscriptionFactory::class, Subscription::factory());
    }

    public function test_fillable_contains_all_route_subscription_fields(): void
    {
        // Mutation intent: `RemoveArrayItem` on `$fillable` (~18–25).
        $expected = [
            'user_id', 'trip_date',
            'from_address', 'from_json_address', 'from_lat', 'from_lng', 'from_radio',
            'to_address', 'to_json_address', 'to_lat', 'to_lng', 'to_radio',
            'state', 'from_id', 'to_id', 'is_passenger',
        ];

        $this->assertEqualsCanonicalizing($expected, (new Subscription)->getFillable());
    }

    public function test_mass_assignment_persists_every_fillable_column(): void
    {
        // Mutation intent: each `$fillable` key must remain assignable; dropping one leaves DB defaults / nulls.
        $user = User::factory()->create();
        $tripDate = Carbon::parse('2026-08-15 09:45:00');

        $payload = [
            'user_id' => $user->id,
            'trip_date' => $tripDate,
            'from_address' => 'Origen 123',
            'from_json_address' => ['city' => 'Rosario', 'code' => 2000],
            'from_lat' => -32.9468,
            'from_lng' => -60.6393,
            'from_radio' => 4.5,
            'to_address' => 'Destino 456',
            'to_json_address' => ['city' => 'San Lorenzo'],
            'to_lat' => -32.75,
            'to_lng' => -60.73,
            'to_radio' => 3.0,
            'state' => true,
            'from_id' => 501,
            'to_id' => 502,
            'is_passenger' => true,
        ];

        $this->assertEqualsCanonicalizing(
            (new Subscription)->getFillable(),
            array_keys($payload),
            'Payload must exercise every fillable key exactly once.'
        );

        $subscription = Subscription::create($payload);
        $row = $subscription->fresh();

        $this->assertSame($user->id, (int) $row->user_id);
        $this->assertSame('2026-08-15', $row->trip_date->toDateString());
        $this->assertSame('Origen 123', $row->from_address);
        $this->assertSame('Rosario', $row->from_json_address['city']);
        $this->assertSame(2000, $row->from_json_address['code']);
        $this->assertEqualsWithDelta(-32.9468, (float) $row->from_lat, 1e-6);
        $this->assertEqualsWithDelta(-60.6393, (float) $row->from_lng, 1e-6);
        $this->assertEqualsWithDelta(4.5, (float) $row->from_radio, 1e-6);
        $this->assertSame('Destino 456', $row->to_address);
        $this->assertSame('San Lorenzo', $row->to_json_address['city']);
        $this->assertEqualsWithDelta(-32.75, (float) $row->to_lat, 1e-6);
        $this->assertEqualsWithDelta(-60.73, (float) $row->to_lng, 1e-6);
        $this->assertEqualsWithDelta(3.0, (float) $row->to_radio, 1e-6);
        $this->assertTrue((bool) $row->state);
        $this->assertSame(501, (int) $row->from_id);
        $this->assertSame(502, (int) $row->to_id);
        $this->assertTrue((bool) $row->is_passenger);
    }

    public function test_casts_include_trip_date_and_json_address_columns(): void
    {
        // Mutation intent: `AlwaysReturnEmptyArray` / `RemoveArrayItem` on `casts()` (~27–32).
        $casts = (new Subscription)->getCasts();

        $this->assertSame('datetime', $casts['trip_date']);
        $this->assertSame('array', $casts['from_json_address']);
        $this->assertSame('array', $casts['to_json_address']);
    }

    public function test_user_relation_returns_belongs_to(): void
    {
        // Mutation intent: `AlwaysReturnNull` on `user()` (~38 RemoveMethodCall / return relation).
        $subscription = new Subscription;

        $this->assertInstanceOf(BelongsTo::class, $subscription->user());
    }

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($subscription->user->is($user));
    }

    public function test_trip_date_and_json_address_casts(): void
    {
        $user = User::factory()->create();
        $tripDate = '2026-03-01 08:30:00';

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'trip_date' => $tripDate,
            'from_json_address' => ['city' => 'Rosario', 'code' => 12],
            'to_json_address' => ['city' => 'Baigorria'],
        ]);

        $subscription = $subscription->fresh();
        $this->assertInstanceOf(Carbon::class, $subscription->trip_date);
        $this->assertSame('2026-03-01', $subscription->trip_date->toDateString());
        $this->assertSame('Rosario', $subscription->from_json_address['city']);
        $this->assertSame(12, $subscription->from_json_address['code']);
        $this->assertSame('Baigorria', $subscription->to_json_address['city']);
    }

    public function test_lat_lng_mutators_persist_trig_helper_columns(): void
    {
        $user = User::factory()->create();
        $fromLat = 10.5;
        $fromLng = -20.25;
        $toLat = 0.0;
        $toLng = 90.0;

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'from_lat' => $fromLat,
            'from_lng' => $fromLng,
            'to_lat' => $toLat,
            'to_lng' => $toLng,
        ]);
        $subscription = $subscription->fresh();
        $attrs = $subscription->getAttributes();

        $this->assertEqualsWithDelta(sin(deg2rad($fromLat)), (float) $attrs['from_sin_lat'], 1e-9);
        $this->assertEqualsWithDelta(cos(deg2rad($fromLat)), (float) $attrs['from_cos_lat'], 1e-9);
        $this->assertEqualsWithDelta(sin(deg2rad($fromLng)), (float) $attrs['from_sin_lng'], 1e-9);
        $this->assertEqualsWithDelta(cos(deg2rad($fromLng)), (float) $attrs['from_cos_lng'], 1e-9);

        $this->assertEqualsWithDelta(sin(deg2rad($toLat)), (float) $attrs['to_sin_lat'], 1e-9);
        $this->assertEqualsWithDelta(cos(deg2rad($toLat)), (float) $attrs['to_cos_lat'], 1e-9);
        $this->assertEqualsWithDelta(sin(deg2rad($toLng)), (float) $attrs['to_sin_lng'], 1e-9);
        $this->assertEqualsWithDelta(cos(deg2rad($toLng)), (float) $attrs['to_cos_lng'], 1e-9);
    }

    public function test_hidden_attributes_list_covers_timestamp_columns(): void
    {
        // Mutation intent: `RemoveArrayItem` on `getHidden()` return list must keep both timestamp keys hidden.
        $this->assertSame(['created_at', 'updated_at'], (new Subscription)->getHidden());
    }

    public function test_to_array_hides_timestamps(): void
    {
        $subscription = Subscription::factory()->create();
        $array = $subscription->toArray();

        $this->assertArrayNotHasKey('created_at', $array);
        $this->assertArrayNotHasKey('updated_at', $array);
        $this->assertArrayHasKey('user_id', $array);
        $this->assertArrayHasKey('state', $array);
    }

    public function test_updates_trig_columns_when_lat_lng_change(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'from_lat' => 1.0,
            'from_lng' => 2.0,
        ]);

        $subscription->from_lat = 45.0;
        $subscription->from_lng = 90.0;
        $subscription->save();
        $subscription = $subscription->fresh();
        $attrs = $subscription->getAttributes();

        $this->assertEqualsWithDelta(sin(deg2rad(45.0)), (float) $attrs['from_sin_lat'], 1e-9);
        $this->assertEqualsWithDelta(sin(deg2rad(90.0)), (float) $attrs['from_sin_lng'], 1e-9);
    }
}
