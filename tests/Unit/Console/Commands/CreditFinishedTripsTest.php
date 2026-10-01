<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class CreditFinishedTripsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2028-06-15 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_credits_driver_when_trip_date_has_passed(): void
    {
        $driver = User::factory()->create(['trips_count' => null]);
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->subHour(),
            'trips_count_credited_at' => null,
        ]);

        $this->assertNull($driver->fresh()->trips_count);

        Artisan::call('trips:credit-finished');

        $driver->refresh();
        $this->assertNotNull($driver->trips_count);
        $this->assertEquals(1, $driver->trips_count);
        $this->assertNotNull($trip->fresh()->trips_count_credited_at);
    }

    public function test_credits_accepted_passengers_when_trip_date_has_passed(): void
    {
        $driver = User::factory()->create(['trips_count' => null]);
        $passenger1 = User::factory()->create(['trips_count' => null]);
        $passenger2 = User::factory()->create(['trips_count' => null]);

        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->subHour(),
            'trips_count_credited_at' => null,
        ]);

        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger1->id,
            'request_state' => Passenger::STATE_ACCEPTED,
        ]);

        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passenger2->id,
            'request_state' => Passenger::STATE_ACCEPTED,
        ]);

        $this->assertNull($passenger1->fresh()->trips_count);
        $this->assertNull($passenger2->fresh()->trips_count);

        Artisan::call('trips:credit-finished');

        $passenger1->refresh();
        $passenger2->refresh();

        $this->assertNotNull($passenger1->trips_count);
        $this->assertNotNull($passenger2->trips_count);
        $this->assertEquals(1, $passenger1->trips_count);
        $this->assertEquals(1, $passenger2->trips_count);
    }

    public function test_does_not_credit_pending_passengers(): void
    {
        $driver = User::factory()->create(['trips_count' => null]);
        $pendingPassenger = User::factory()->create(['trips_count' => null]);

        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->subHour(),
            'trips_count_credited_at' => null,
        ]);

        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $pendingPassenger->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);

        Artisan::call('trips:credit-finished');

        $pendingPassenger->refresh();
        $this->assertNull($pendingPassenger->trips_count);
    }

    public function test_does_not_credit_rejected_passengers(): void
    {
        $driver = User::factory()->create(['trips_count' => null]);
        $rejectedPassenger = User::factory()->create(['trips_count' => null]);

        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->subHour(),
            'trips_count_credited_at' => null,
        ]);

        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $rejectedPassenger->id,
            'request_state' => Passenger::STATE_REJECTED,
        ]);

        Artisan::call('trips:credit-finished');

        $rejectedPassenger->refresh();
        $this->assertNull($rejectedPassenger->trips_count);
    }

    public function test_does_not_credit_soft_deleted_trips(): void
    {
        $driver = User::factory()->create(['trips_count' => null]);

        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->subHour(),
            'trips_count_credited_at' => null,
        ]);

        $trip->delete(); // Soft delete

        Artisan::call('trips:credit-finished');

        $driver->refresh();
        $this->assertNull($driver->trips_count);
        $this->assertNull($trip->fresh()->trips_count_credited_at);
    }

    public function test_does_not_credit_future_trips(): void
    {
        $driver = User::factory()->create(['trips_count' => null]);

        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDay(),
            'trips_count_credited_at' => null,
        ]);

        Artisan::call('trips:credit-finished');

        $driver->refresh();
        $this->assertNull($driver->trips_count);
        $this->assertNull($trip->fresh()->trips_count_credited_at);
    }

    public function test_does_not_credit_already_credited_trips(): void
    {
        $driver = User::factory()->create(['trips_count' => 1]);

        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->subHour(),
            'trips_count_credited_at' => Carbon::now()->subMinutes(10),
        ]);

        Artisan::call('trips:credit-finished');

        $driver->refresh();
        $this->assertEquals(1, $driver->trips_count);
    }

    public function test_credits_multiple_trips_in_one_run(): void
    {
        $driver1 = User::factory()->create(['trips_count' => null]);
        $driver2 = User::factory()->create(['trips_count' => null]);

        Trip::factory()->create([
            'user_id' => $driver1->id,
            'trip_date' => Carbon::now()->subHour(),
            'trips_count_credited_at' => null,
        ]);

        Trip::factory()->create([
            'user_id' => $driver2->id,
            'trip_date' => Carbon::now()->subDays(2),
            'trips_count_credited_at' => null,
        ]);

        Artisan::call('trips:credit-finished');

        $driver1->refresh();
        $driver2->refresh();

        $this->assertEquals(1, $driver1->trips_count);
        $this->assertEquals(1, $driver2->trips_count);
    }

    public function test_credits_at_most_one_hundred_trips_per_run(): void
    {
        $trips = [];
        for ($i = 0; $i < 101; $i++) {
            $driver = User::factory()->create(['trips_count' => null]);
            $trips[] = Trip::factory()->create([
                'user_id' => $driver->id,
                'trip_date' => Carbon::now()->subHour(),
                'trips_count_credited_at' => null,
            ]);
        }

        Artisan::call('trips:credit-finished');

        $credited = collect($trips)
            ->filter(fn (Trip $trip) => $trip->fresh()->trips_count_credited_at !== null)
            ->count();

        $this->assertSame(100, $credited);
    }

    public function test_marks_trip_as_credited_with_correct_timestamp(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->subHour(),
            'trips_count_credited_at' => null,
        ]);

        $beforeRun = Carbon::now();
        Artisan::call('trips:credit-finished');
        $afterRun = Carbon::now();

        $trip->refresh();
        $this->assertNotNull($trip->trips_count_credited_at);
        $this->assertTrue($trip->trips_count_credited_at->between($beforeRun, $afterRun));
    }
}
