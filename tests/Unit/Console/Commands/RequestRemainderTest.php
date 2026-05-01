<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use STS\Console\Commands\RequestRemainder;
use STS\Events\Trip\Alert\RequestRemainder as RequestRemainderEvent;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class RequestRemainderTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_handle_dispatches_events_for_last_week_and_even_days_in_second_week(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 10, 0, 0));
        Event::fake([MessageLogged::class, RequestRemainderEvent::class]);

        $driver = User::factory()->create();

        $tripSixDays = Trip::factory()->create([
            'user_id' => $driver->id,
            'is_passenger' => 0,
            'trip_date' => Carbon::now()->copy()->addDays(6),
        ]);
        $tripTenDays = Trip::factory()->create([
            'user_id' => $driver->id,
            'is_passenger' => 0,
            'trip_date' => Carbon::now()->copy()->addDays(10),
        ]);
        Trip::factory()->create([
            'user_id' => $driver->id,
            'is_passenger' => 0,
            'trip_date' => Carbon::now()->copy()->addDays(9),
        ]);
        Trip::factory()->create([
            'user_id' => $driver->id,
            'is_passenger' => 0,
            'trip_date' => Carbon::now()->copy()->addDays(20),
        ]);
        Passenger::factory()->create([
            'trip_id' => $tripSixDays->id,
            'user_id' => User::factory()->create()->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);
        Passenger::factory()->create([
            'trip_id' => $tripTenDays->id,
            'user_id' => User::factory()->create()->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);

        $this->artisan('trip:request')->assertExitCode(0);

        Event::assertDispatched(RequestRemainderEvent::class, function (RequestRemainderEvent $event) use ($tripSixDays) {
            return $event->trip->id === $tripSixDays->id;
        });
        Event::assertDispatched(RequestRemainderEvent::class, function (RequestRemainderEvent $event) use ($tripTenDays) {
            return $event->trip->id === $tripTenDays->id;
        });
        Event::assertDispatchedTimes(RequestRemainderEvent::class, 2);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $e): bool {
            return $e->level === 'info' && $e->message === 'COMMAND RequestRemainder';
        });
    }

    public function test_handle_does_not_include_trips_without_pending_passengers(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 10, 0, 0));
        Event::fake([MessageLogged::class, RequestRemainderEvent::class]);

        $driver = User::factory()->create();
        Trip::factory()->create([
            'user_id' => $driver->id,
            'is_passenger' => 0,
            'trip_date' => Carbon::now()->copy()->addDays(5),
        ]);

        $this->artisan('trip:request')->assertExitCode(0);

        Event::assertNotDispatched(RequestRemainderEvent::class);
    }

    public function test_command_contract_is_defined(): void
    {
        $command = new RequestRemainder;

        $this->assertSame('trip:request', $command->getName());
        $this->assertStringContainsString('Notify pending requests', $command->getDescription());
    }
}
