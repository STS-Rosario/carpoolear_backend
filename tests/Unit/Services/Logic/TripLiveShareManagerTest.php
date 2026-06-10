<?php

namespace Tests\Unit\Services\Logic;

use Carbon\Carbon;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\TripLiveShare;
use STS\Models\User;
use STS\Notifications\DriverLiveLocationSharingNotification;
use STS\Services\Logic\TripLiveShareManager;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class TripLiveShareManagerTest extends TestCase
{
    private function manager(): TripLiveShareManager
    {
        return $this->app->make(TripLiveShareManager::class);
    }

    private function ongoingTripFor(User $driver, ?Carbon $tripDate = null): Trip
    {
        return Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => $tripDate ?? Carbon::parse('2026-06-02 16:00:00'),
            'estimated_time' => '01:00',
        ]);
    }

    public function test_start_allows_accepted_passenger_within_sharing_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 15:30:00'));
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = $this->ongoingTripFor($driver);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
        ]);

        $share = $this->manager()->start($passenger, $trip->id);

        $this->assertInstanceOf(TripLiveShare::class, $share);
        $this->assertTrue($share->is_active);
        $this->assertGreaterThanOrEqual(48, strlen($share->share_token));
        $this->assertSame($passenger->id, $share->user_id);
    }

    public function test_start_allows_driver_within_sharing_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 15:30:00'));
        $driver = User::factory()->create();
        $trip = $this->ongoingTripFor($driver);

        $share = $this->manager()->start($driver, $trip->id);

        $this->assertInstanceOf(TripLiveShare::class, $share);
        $this->assertTrue($share->is_active);
    }

    public function test_start_denies_outsider(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 15:30:00'));
        $driver = User::factory()->create();
        $trip = $this->ongoingTripFor($driver);
        $outsider = User::factory()->create();

        $manager = $this->manager();
        $this->assertNull($manager->start($outsider, $trip->id));
        $this->assertSame('access_denied', $manager->getErrors()['error']);
    }

    public function test_start_denies_before_sharing_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 14:00:00'));
        $driver = User::factory()->create();
        $trip = $this->ongoingTripFor($driver);

        $manager = $this->manager();
        $this->assertNull($manager->start($driver, $trip->id));
        $this->assertSame('sharing_not_available', $manager->getErrors()['error']);
    }

    public function test_start_resumes_existing_share_without_new_token(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 15:30:00'));
        $driver = User::factory()->create();
        $trip = $this->ongoingTripFor($driver);
        $existing = TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $driver->id,
            'share_token' => 'existing-token-123456789012345678901234567890123456789012',
            'is_active' => false,
            'stopped_at' => Carbon::now(),
        ]);

        $share = $this->manager()->start($driver, $trip->id);

        $this->assertSame($existing->id, $share->id);
        $this->assertSame('existing-token-123456789012345678901234567890123456789012', $share->share_token);
        $this->assertTrue($share->is_active);
    }

    public function test_update_location_only_for_active_owner(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 15:30:00'));
        $driver = User::factory()->create();
        $trip = $this->ongoingTripFor($driver);
        $share = TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $driver->id,
            'is_active' => true,
        ]);

        $updated = $this->manager()->updateLocation($driver, $trip->id, -34.6, -58.38);

        $this->assertInstanceOf(TripLiveShare::class, $updated);
        $this->assertSame(-34.6, $updated->lat);
        $this->assertSame(-58.38, $updated->lng);
        $this->assertNotNull($updated->recorded_at);
    }

    public function test_update_location_denies_inactive_share(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 15:30:00'));
        $driver = User::factory()->create();
        $trip = $this->ongoingTripFor($driver);
        TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $driver->id,
            'is_active' => false,
        ]);

        $manager = $this->manager();
        $this->assertNull($manager->updateLocation($driver, $trip->id, -34.6, -58.38));
        $this->assertSame('share_not_active', $manager->getErrors()['error']);
    }

    public function test_update_location_denies_after_auto_stop_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 18:01:00'));
        $driver = User::factory()->create();
        $trip = $this->ongoingTripFor($driver);
        TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $driver->id,
            'is_active' => true,
            'started_at' => Carbon::parse('2026-06-02 16:00:00'),
        ]);

        $manager = $this->manager();
        $this->assertNull($manager->updateLocation($driver, $trip->id, -34.6, -58.38));
        $this->assertSame('sharing_expired', $manager->getErrors()['error']);
    }

    public function test_stop_clears_coordinates_and_deactivates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 15:30:00'));
        $driver = User::factory()->create();
        $trip = $this->ongoingTripFor($driver);
        TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $driver->id,
            'is_active' => true,
            'lat' => -34.6,
            'lng' => -58.38,
            'recorded_at' => Carbon::now(),
        ]);

        $stopped = $this->manager()->stop($driver, $trip->id);

        $this->assertFalse($stopped->is_active);
        $this->assertNull($stopped->lat);
        $this->assertNull($stopped->lng);
        $this->assertNull($stopped->recorded_at);
        $this->assertNotNull($stopped->stopped_at);
    }

    public function test_get_public_view_returns_driver_context_for_active_share(): void
    {
        $driver = User::factory()->create(['name' => 'Juan Driver']);
        $trip = $this->ongoingTripFor($driver);
        $share = TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => User::factory()->create()->id,
            'is_active' => true,
            'lat' => -34.6,
            'lng' => -58.38,
            'recorded_at' => Carbon::now(),
        ]);

        $view = $this->manager()->getPublicView($share->share_token);

        $this->assertNotNull($view);
        $this->assertSame(-34.6, $view['lat']);
        $this->assertSame('Juan Driver', $view['driver']['name']);
        $this->assertArrayHasKey('positive_ratings', $view['driver']);
        $this->assertArrayHasKey('negative_ratings', $view['driver']);
        $this->assertArrayHasKey('neutral_ratings', $view['driver']);
    }

    public function test_get_public_view_returns_null_for_invalid_token(): void
    {
        $this->assertNull($this->manager()->getPublicView('invalid-token'));
    }

    public function test_get_public_view_returns_waiting_state_without_coordinates(): void
    {
        $driver = User::factory()->create(['name' => 'Juan Driver']);
        $trip = $this->ongoingTripFor($driver);
        $share = TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => User::factory()->create()->id,
            'is_active' => true,
            'lat' => null,
            'lng' => null,
        ]);

        $view = $this->manager()->getPublicView($share->share_token);

        $this->assertNotNull($view);
        $this->assertNull($view['lat']);
        $this->assertNull($view['lng']);
        $this->assertNull($view['recorded_at']);
        $this->assertTrue($view['is_active']);
        $this->assertSame('Juan Driver', $view['driver']['name']);
    }

    public function test_get_public_view_returns_stopped_state_for_inactive_share(): void
    {
        $driver = User::factory()->create(['name' => 'Juan Driver']);
        $trip = $this->ongoingTripFor($driver);
        $share = TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => User::factory()->create()->id,
            'is_active' => false,
            'lat' => null,
            'lng' => null,
            'stopped_at' => Carbon::now(),
        ]);

        $view = $this->manager()->getPublicView($share->share_token);

        $this->assertNotNull($view);
        $this->assertFalse($view['is_active']);
        $this->assertNull($view['lat']);
        $this->assertNull($view['lng']);
        $this->assertSame('Juan Driver', $view['driver']['name']);
    }

    public function test_get_public_view_includes_trip_context_for_passenger_share(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-08 12:00:00'));
        $driver = User::factory()->create(['name' => 'Juan Driver']);
        $passenger = User::factory()->create(['name' => 'Ana Passenger']);
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'to_town' => 'Rosario',
            'trip_date' => Carbon::parse('2026-06-08 16:00:00'),
        ]);
        $share = TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'is_active' => true,
            'lat' => -32.9,
            'lng' => -60.6,
        ]);

        $view = $this->manager()->getPublicView($share->share_token);

        $this->assertTrue($view['is_passenger_share']);
        $this->assertSame('Ana Passenger', $view['sharer']['name']);
        $this->assertSame('Juan Driver', $view['driver']['name']);
        $this->assertSame('Rosario', $view['destination']);
        $this->assertSame(
            Carbon::parse('2026-06-08 16:00:00')->toIso8601String(),
            $view['trip_date']
        );
    }

    public function test_get_public_view_marks_driver_share_as_not_passenger_share(): void
    {
        $driver = User::factory()->create(['name' => 'Juan Driver']);
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'to_town' => 'Rosario',
            'trip_date' => Carbon::parse('2026-06-08 16:00:00'),
        ]);
        $share = TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $driver->id,
            'is_active' => true,
        ]);

        $view = $this->manager()->getPublicView($share->share_token);

        $this->assertFalse($view['is_passenger_share']);
    }

    public function test_get_trip_view_returns_stopped_state_for_inactive_driver_share(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 15:30:00'));
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = $this->ongoingTripFor($driver);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
        ]);
        TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $driver->id,
            'is_active' => false,
            'lat' => null,
            'lng' => null,
            'stopped_at' => Carbon::now(),
        ]);

        $view = $this->manager()->getTripView($passenger, $trip->id);

        $this->assertNotNull($view);
        $this->assertFalse($view['is_active']);
        $this->assertNull($view['lat']);
        $this->assertNull($view['lng']);
    }

    public function test_get_trip_view_returns_stopped_state_for_passenger_share(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 15:30:00'));
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = $this->ongoingTripFor($driver);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
        ]);
        TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
            'is_active' => false,
            'lat' => null,
            'lng' => null,
            'stopped_at' => Carbon::now(),
        ]);

        $view = $this->manager()->getTripView($passenger, $trip->id);

        $this->assertNotNull($view);
        $this->assertFalse($view['is_active']);
        $this->assertSame($passenger->id, $view['sharer']['id']);
    }

    public function test_get_trip_view_allows_participant_and_returns_driver_share(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 15:30:00'));
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = $this->ongoingTripFor($driver);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
        ]);
        TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $driver->id,
            'is_active' => true,
            'lat' => -34.5,
            'lng' => -58.4,
            'recorded_at' => Carbon::now(),
        ]);

        $view = $this->manager()->getTripView($passenger, $trip->id);

        $this->assertNotNull($view);
        $this->assertSame(-34.5, $view['lat']);
    }

    public function test_get_trip_view_denies_outsider(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 15:30:00'));
        $driver = User::factory()->create();
        $trip = $this->ongoingTripFor($driver);
        $outsider = User::factory()->create();

        $manager = $this->manager();
        $this->assertNull($manager->getTripView($outsider, $trip->id));
        $this->assertSame('access_denied', $manager->getErrors()['error']);
    }

    public function test_driver_start_notifies_accepted_passengers_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 15:30:00'));
        $driver = User::factory()->create(['name' => 'Driver']);
        $passenger = User::factory()->create();
        $trip = $this->ongoingTripFor($driver);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
        ]);

        $notifiedUserIds = [];
        $mock = $this->mock(NotificationServices::class);
        $mock->shouldReceive('send')
            ->andReturnUsing(function ($notification, $users) use (&$notifiedUserIds) {
                if ($notification instanceof DriverLiveLocationSharingNotification && $users instanceof User) {
                    $notifiedUserIds[] = $users->id;
                }
            });

        $this->manager()->start($driver, $trip->id);

        $this->assertSame([$passenger->id], array_values(array_unique($notifiedUserIds)));
    }

    public function test_driver_start_does_not_notify_driver_even_with_accepted_passenger_row(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 15:30:00'));
        $driver = User::factory()->create(['name' => 'Driver']);
        $passenger = User::factory()->create();
        $trip = $this->ongoingTripFor($driver);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
        ]);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $driver->id,
        ]);

        $notifiedUserIds = [];
        $mock = $this->mock(NotificationServices::class);
        $mock->shouldReceive('send')
            ->andReturnUsing(function ($notification, $users) use (&$notifiedUserIds) {
                if ($notification instanceof DriverLiveLocationSharingNotification && $users instanceof User) {
                    $notifiedUserIds[] = $users->id;
                }
            });

        $this->manager()->start($driver, $trip->id);

        $this->assertSame([$passenger->id], array_values(array_unique($notifiedUserIds)));
        $this->assertNotContains($driver->id, $notifiedUserIds);
    }

    public function test_passenger_start_does_not_notify_anyone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 15:30:00'));
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $trip = $this->ongoingTripFor($driver);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
        ]);

        $mock = $this->mock(NotificationServices::class);
        $mock->shouldReceive('send')
            ->never()
            ->withArgs(function ($notification) {
                return $notification instanceof DriverLiveLocationSharingNotification;
            });

        $this->manager()->start($passenger, $trip->id);
    }

    public function test_admin_passenger_start_does_not_notify_anyone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 15:30:00'));
        $driver = User::factory()->create();
        $adminPassenger = User::factory()->create(['is_admin' => true]);
        $trip = $this->ongoingTripFor($driver);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $adminPassenger->id,
        ]);

        $mock = $this->mock(NotificationServices::class);
        $mock->shouldReceive('send')
            ->never()
            ->withArgs(function ($notification) {
                return $notification instanceof DriverLiveLocationSharingNotification;
            });

        $this->manager()->start($adminPassenger, $trip->id);
    }
}
