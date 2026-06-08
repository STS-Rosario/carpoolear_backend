<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\TripLiveShare;
use STS\Models\TripPoint;
use STS\Models\User;
use STS\Notifications\DriverLiveLocationSharingNotification;
use STS\Notifications\LiveLocationAutoStoppedNotification;
use STS\Notifications\LiveLocationStopReminderNotification;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class LiveLocationProcessCommandTest extends TestCase
{
    public function test_command_sends_stop_reminder_once_when_past_eta_and_near_destination(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 17:30:00'));
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::parse('2026-06-02 16:00:00'),
            'estimated_time' => '01:00',
        ]);
        TripPoint::factory()->create([
            'trip_id' => $trip->id,
            'lat' => -34.6037,
            'lng' => -58.3816,
            'address' => 'Origin',
        ]);
        TripPoint::factory()->create([
            'trip_id' => $trip->id,
            'lat' => -34.6037,
            'lng' => -58.3816,
            'address' => 'Destination',
        ]);
        $share = TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $driver->id,
            'is_active' => true,
            'lat' => -34.6037 + (5.0 / 111.0),
            'lng' => -58.3816,
            'recorded_at' => Carbon::now(),
            'started_at' => Carbon::parse('2026-06-02 16:00:00'),
        ]);

        $mock = $this->mock(NotificationServices::class);
        $mock->shouldReceive('send')
            ->atLeast()
            ->once()
            ->withArgs(function ($notification, $users, $channel) use ($driver) {
                return $notification instanceof LiveLocationStopReminderNotification
                    && $users instanceof User
                    && $users->id === $driver->id;
            });

        $this->artisan('live-location:process')->assertSuccessful();

        $share->refresh();
        $this->assertNotNull($share->stop_reminder_sent_at);
    }

    public function test_command_auto_stops_share_after_twice_estimated_duration(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 18:01:00'));
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::parse('2026-06-02 16:00:00'),
            'estimated_time' => '01:00',
        ]);
        $share = TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $driver->id,
            'is_active' => true,
            'lat' => -34.6,
            'lng' => -58.38,
            'recorded_at' => Carbon::now(),
            'started_at' => Carbon::parse('2026-06-02 16:00:00'),
        ]);

        $mock = $this->mock(NotificationServices::class);
        $mock->shouldReceive('send')
            ->atLeast()
            ->once()
            ->withArgs(function ($notification, $users, $channel) use ($driver) {
                return $notification instanceof LiveLocationAutoStoppedNotification
                    && $users instanceof User
                    && $users->id === $driver->id;
            });

        $this->artisan('live-location:process')->assertSuccessful();

        $share->refresh();
        $this->assertFalse($share->is_active);
        $this->assertNull($share->lat);
        $this->assertNotNull($share->auto_stopped_at);
    }

    public function test_start_after_auto_stop_resumes_sharing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 18:30:00'));
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::parse('2026-06-02 16:00:00'),
            'estimated_time' => '01:00',
        ]);
        TripLiveShare::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $driver->id,
            'is_active' => false,
            'auto_stopped_at' => Carbon::parse('2026-06-02 18:01:00'),
            'share_token' => 'resume-token-123456789012345678901234567890123456789012',
        ]);

        $manager = $this->app->make(\STS\Services\Logic\TripLiveShareManager::class);
        $share = $manager->start($driver, $trip->id);

        $this->assertTrue($share->is_active);
        $this->assertSame('resume-token-123456789012345678901234567890123456789012', $share->share_token);
    }

    public function test_driver_start_notifies_accepted_passengers(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-02 15:30:00'));
        $driver = User::factory()->create(['name' => 'Driver']);
        $passenger = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::parse('2026-06-02 16:00:00'),
            'estimated_time' => '01:00',
            'to_town' => 'Mendoza',
        ]);
        Passenger::factory()->aceptado()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger->id,
        ]);

        $mock = $this->mock(NotificationServices::class);
        $mock->shouldReceive('send')
            ->atLeast()
            ->once()
            ->withArgs(function ($notification, $users, $channel) use ($passenger) {
                return $notification instanceof DriverLiveLocationSharingNotification
                    && $users instanceof User
                    && $users->id === $passenger->id;
            });

        $manager = $this->app->make(\STS\Services\Logic\TripLiveShareManager::class);
        $manager->start($driver, $trip->id);
    }
}
