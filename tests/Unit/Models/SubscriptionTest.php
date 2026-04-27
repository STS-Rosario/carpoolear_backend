<?php

namespace Tests\Unit\Models;

use Carbon\Carbon;
use STS\Models\Subscription;
use STS\Models\User;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
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
