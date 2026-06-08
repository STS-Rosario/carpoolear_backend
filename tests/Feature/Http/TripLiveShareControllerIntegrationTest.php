<?php

namespace Tests\Feature\Http;

use Carbon\Carbon;
use STS\Models\Trip;
use STS\Models\TripLiveShare;
use STS\Models\User;
use Tests\TestCase;

class TripLiveShareControllerIntegrationTest extends TestCase
{
    public function test_start_live_share_returns_token(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 15:30:00'));
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::parse('2026-06-02 16:00:00'),
            'estimated_time' => '01:00',
        ]);

        $response = $this->actingAs($driver, 'api')
            ->postJson("/api/trips/{$trip->id}/live-share/start");

        $response->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonStructure(['data' => ['share_token', 'is_active']]);
    }

    public function test_update_location_persists_coordinates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 15:30:00'));
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::parse('2026-06-02 16:00:00'),
            'estimated_time' => '01:00',
        ]);
        TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $driver->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($driver, 'api')
            ->putJson("/api/trips/{$trip->id}/live-share/location", [
                'lat' => -34.6,
                'lng' => -58.38,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.lat', -34.6)
            ->assertJsonPath('data.lng', -58.38)
            ->assertJsonPath('data.recorded_at', Carbon::now()->toIso8601String());
    }

    public function test_status_returns_recorded_at_for_active_share(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 15:30:00'));
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::parse('2026-06-02 16:00:00'),
            'estimated_time' => '01:00',
        ]);
        TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $driver->id,
            'is_active' => true,
            'lat' => -34.6,
            'lng' => -58.38,
            'recorded_at' => Carbon::parse('2026-06-02 15:30:00'),
        ]);

        $response = $this->actingAs($driver, 'api')
            ->getJson("/api/trips/{$trip->id}/live-share");

        $response->assertOk()
            ->assertJsonPath('data.recorded_at', Carbon::parse('2026-06-02 15:30:00')->toIso8601String());
    }

    public function test_stop_live_share_clears_coordinates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 15:30:00'));
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::parse('2026-06-02 16:00:00'),
            'estimated_time' => '01:00',
        ]);
        TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $driver->id,
            'is_active' => true,
            'lat' => -34.6,
            'lng' => -58.38,
        ]);

        $response = $this->actingAs($driver, 'api')
            ->postJson("/api/trips/{$trip->id}/live-share/stop");

        $response->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.lat', null);
    }

    public function test_public_live_endpoint_returns_location_without_auth(): void
    {
        $driver = User::factory()->create(['name' => 'Driver Name']);
        $trip = Trip::factory()->create(['user_id' => $driver->id, 'to_town' => 'Rosario']);
        $share = TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => User::factory()->create()->id,
            'is_active' => true,
            'lat' => -32.9,
            'lng' => -60.6,
            'recorded_at' => Carbon::now(),
        ]);

        $response = $this->getJson("/api/live/{$share->share_token}");

        $response->assertOk()
            ->assertJsonPath('data.lat', -32.9)
            ->assertJsonPath('data.driver.name', 'Driver Name')
            ->assertJsonPath('data.recorded_at', $share->recorded_at->toIso8601String());
    }

    public function test_public_live_endpoint_returns_not_found_for_invalid_token(): void
    {
        $this->getJson('/api/live/invalid-token-value')->assertNotFound();
    }

    public function test_public_live_endpoint_returns_waiting_state_without_coordinates(): void
    {
        $driver = User::factory()->create(['name' => 'Driver Name']);
        $trip = Trip::factory()->create(['user_id' => $driver->id, 'to_town' => 'Rosario']);
        $share = TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => User::factory()->create()->id,
            'is_active' => true,
            'lat' => null,
            'lng' => null,
        ]);

        $response = $this->getJson("/api/live/{$share->share_token}");

        $response->assertOk()
            ->assertJsonPath('data.lat', null)
            ->assertJsonPath('data.lng', null)
            ->assertJsonPath('data.driver.name', 'Driver Name');
    }

    public function test_trip_view_requires_participant(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 15:30:00'));
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::parse('2026-06-02 16:00:00'),
            'estimated_time' => '01:00',
        ]);
        TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $driver->id,
            'is_active' => true,
            'lat' => -34.6,
            'lng' => -58.38,
            'recorded_at' => Carbon::now(),
        ]);
        $outsider = User::factory()->create();

        $this->actingAs($outsider, 'api')
            ->getJson("/api/trips/{$trip->id}/live-share/view")
            ->assertStatus(422)
            ->assertJsonPath('errors.error', 'access_denied');
    }

    public function test_trip_view_returns_recorded_at_for_participant(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 15:30:00'));
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::parse('2026-06-02 16:00:00'),
            'estimated_time' => '01:00',
        ]);
        TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $driver->id,
            'is_active' => true,
            'lat' => -34.6,
            'lng' => -58.38,
            'recorded_at' => Carbon::parse('2026-06-02 15:30:00'),
        ]);

        $response = $this->actingAs($driver, 'api')
            ->getJson("/api/trips/{$trip->id}/live-share/view");

        $response->assertOk()
            ->assertJsonPath('data.recorded_at', Carbon::parse('2026-06-02 15:30:00')->toIso8601String());
    }
}
