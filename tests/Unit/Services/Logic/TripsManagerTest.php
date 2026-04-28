<?php

namespace Tests\Unit\Services\Logic;

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use STS\Events\Trip\Delete as DeleteEvent;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\FriendsRepository;
use STS\Repository\TripRepository;
use STS\Services\Logic\FriendsManager;
use STS\Services\Logic\TripsManager;
use Tests\TestCase;

class TripsManagerTest extends TestCase
{
    private function manager(): TripsManager
    {
        return $this->app->make(TripsManager::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalCreatePayload(): array
    {
        return [
            'is_passenger' => 0,
            'from_town' => 'Origin Town',
            'to_town' => 'Destination Town',
            'trip_date' => '2028-03-10 15:00:00',
            'total_seats' => 3,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'estimated_time' => '01:30',
            'distance' => 100,
            'co2' => 10,
            'description' => 'unit test trip',
            'points' => [
                [
                    'address' => 'Point A',
                    'json_address' => ['name' => 'A', 'state' => 'BA'],
                    'lat' => -34.6,
                    'lng' => -58.4,
                ],
                [
                    'address' => 'Point B',
                    'json_address' => ['name' => 'B', 'state' => 'BA'],
                    'lat' => -34.7,
                    'lng' => -58.5,
                ],
            ],
        ];
    }

    public function test_validator_create_requires_core_fields(): void
    {
        Carbon::setTestNow('2028-01-01 12:00:00');
        $user = User::factory()->create();
        $v = $this->manager()->validator([], $user->id);
        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('is_passenger'));
        $this->assertTrue($v->errors()->has('from_town'));
        Carbon::setTestNow();
    }

    public function test_validator_create_passes_with_future_trip_date_and_points(): void
    {
        Carbon::setTestNow('2028-01-01 12:00:00');
        $user = User::factory()->create();
        $v = $this->manager()->validator($this->minimalCreatePayload(), $user->id);
        $this->assertFalse($v->fails());
        Carbon::setTestNow();
    }

    public function test_validator_create_rejects_trip_date_not_after_now(): void
    {
        Carbon::setTestNow('2028-06-01 12:00:00');
        $user = User::factory()->create();
        $payload = $this->minimalCreatePayload();
        $payload['trip_date'] = '2028-05-01 10:00:00';
        $v = $this->manager()->validator($payload, $user->id);
        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('trip_date'));
        Carbon::setTestNow();
    }

    public function test_create_returns_null_when_validation_fails(): void
    {
        Carbon::setTestNow('2028-02-01 10:00:00');
        $user = User::factory()->create();
        $manager = $this->manager();
        $this->assertNull($manager->create($user, ['is_passenger' => 0]));
        $this->assertTrue($manager->getErrors()->has('from_town'));
        Carbon::setTestNow();
    }

    public function test_trip_owner_true_for_driver_and_admin(): void
    {
        $driver = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $m = $this->manager();
        $this->assertTrue($m->tripOwner($driver, $trip));
        $this->assertTrue($m->tripOwner($admin, $trip));
        $this->assertFalse($m->tripOwner($other, $trip));
    }

    public function test_exist_always_returns_true(): void
    {
        $this->assertTrue(TripsManager::exist(12345));
    }

    public function test_price_uses_simple_price_when_api_price_disabled(): void
    {
        config(['carpoolear.api_price' => false, 'carpoolear.fuel_price' => 1000]);
        $manager = $this->manager();
        $distance = 2000;
        $repo = $this->app->make(TripRepository::class);
        $this->assertSame($repo->simplePrice($distance), $manager->price(null, null, $distance));
    }

    public function test_user_can_see_public_trip_as_non_owner(): void
    {
        $driver = User::factory()->create();
        $stranger = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
        ]);

        $this->assertTrue($this->manager()->userCanSeeTrip($stranger, $trip));
    }

    public function test_user_can_see_friends_trip_only_when_friends(): void
    {
        $driver = User::factory()->create();
        $friend = User::factory()->create();
        $stranger = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
        ]);

        $this->assertFalse($this->manager()->userCanSeeTrip($stranger, $trip));

        (new FriendsManager(new FriendsRepository))->make($driver, $friend);

        $this->assertTrue($this->manager()->userCanSeeTrip($friend->fresh(), $trip));
    }

    public function test_show_returns_trip_when_user_can_see_it(): void
    {
        $driver = User::factory()->create();
        $viewer = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
        ]);

        $shown = $this->manager()->show($viewer, $trip->id);
        $this->assertNotNull($shown);
        $this->assertTrue($shown->is($trip));
    }

    public function test_show_returns_null_when_user_cannot_see_private_trip(): void
    {
        $driver = User::factory()->create();
        $stranger = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
        ]);

        $manager = $this->manager();
        $this->assertNull($manager->show($stranger, $trip->id));
        $this->assertSame('trip_not_foound', $manager->getErrors()['error']);
    }

    public function test_change_trip_seats_updates_when_owner_and_within_bounds(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'total_seats' => 2,
        ]);

        $updated = $this->manager()->changeTripSeats($driver, $trip->id, 1);
        $this->assertNotNull($updated);
        $this->assertSame(3, (int) $updated->fresh()->total_seats);
    }

    public function test_change_trip_seats_rejects_when_exceeds_four(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'total_seats' => 4,
        ]);

        $manager = $this->manager();
        $this->assertNull($manager->changeTripSeats($driver, $trip->id, 1));
        $this->assertSame('trip_seats_less_than_four', $manager->getErrors()['error']);
    }

    public function test_change_trip_seats_rejects_negative_total(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'total_seats' => 1,
        ]);

        $manager = $this->manager();
        $this->assertNull($manager->changeTripSeats($driver, $trip->id, -2));
        $this->assertSame('trip_seats_greater_than_zero', $manager->getErrors()['error']);
    }

    public function test_delete_dispatches_delete_event_and_soft_deletes(): void
    {
        Event::fake([DeleteEvent::class]);
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $this->assertTrue((bool) $this->manager()->delete($driver, $trip->id));

        Event::assertDispatched(DeleteEvent::class);
        $this->assertNotNull($trip->fresh()->deleted_at);
    }

    public function test_update_rejects_total_seats_below_accepted_passengers(): void
    {
        Carbon::setTestNow('2028-04-01 10:00:00');
        $driver = User::factory()->create();
        $pax1 = User::factory()->create();
        $pax2 = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'total_seats' => 4,
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
            'trip_date' => Carbon::parse('2028-05-01 12:00:00'),
        ]);

        Passenger::factory()->aceptado()->create(['trip_id' => $trip->id, 'user_id' => $pax1->id]);
        Passenger::factory()->aceptado()->create(['trip_id' => $trip->id, 'user_id' => $pax2->id]);

        $manager = $this->manager();
        $this->assertNull($manager->update($driver, $trip->id, [
            'total_seats' => 1,
        ]));
        $errors = $manager->getErrors();
        $this->assertIsArray($errors);
        $this->assertSame('trip_invalid_seats', $errors['error']);

        Carbon::setTestNow();
    }

    public function test_share_trip_true_when_either_shared_a_ride(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $a->id,
            'trip_date' => Carbon::now()->addDays(5),
        ]);
        Passenger::factory()->aceptado()->create(['trip_id' => $trip->id, 'user_id' => $b->id]);

        $this->assertTrue($this->manager()->shareTrip($a->fresh(), $b->fresh()));
    }
}
