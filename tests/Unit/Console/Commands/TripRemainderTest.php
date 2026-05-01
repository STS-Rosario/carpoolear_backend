<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Mockery;
use STS\Console\Commands\TripRemainder;
use STS\Events\Trip\Alert\HourLeft as HourLeftEvent;
use STS\Repository\TripRepository;
use STS\Services\Logic\TripsManager;
use Tests\TestCase;

class TripRemainderTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_dispatches_hour_left_for_driver_and_each_accepted_passenger(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 9, 25, 0));
        Event::fake([HourLeftEvent::class, MessageLogged::class]);

        $driver = (object) ['id' => 10];
        $passengerA = (object) ['id' => 20];
        $passengerB = (object) ['id' => 30];

        $tripWithPassengers = (object) [
            'id' => 1,
            'user' => $driver,
            'passengerAccepted' => collect([
                (object) ['user' => $passengerA],
                (object) ['user' => $passengerB],
            ]),
        ];
        $tripWithoutPassengers = (object) [
            'id' => 2,
            'user' => (object) ['id' => 40],
            'passengerAccepted' => collect(),
        ];

        $repo = Mockery::mock(TripRepository::class);
        $repo->shouldReceive('index')
            ->once()
            ->with([
                ['key' => 'trip_date', 'op' => '>=', 'value' => '2026-04-28 10:00:00'],
                ['key' => 'trip_date', 'op' => '<=', 'value' => '2026-04-28 10:59:59'],
            ], ['user', 'passengerAccepted'])
            ->andReturn(new Collection([$tripWithPassengers, $tripWithoutPassengers]));

        $logic = Mockery::mock(TripsManager::class);
        $command = new TripRemainder($logic, $repo);
        $command->handle();

        Event::assertDispatched(HourLeftEvent::class, function (HourLeftEvent $event) use ($tripWithPassengers, $driver) {
            return $event->trip->id === $tripWithPassengers->id && $event->to->id === $driver->id;
        });
        Event::assertDispatched(HourLeftEvent::class, function (HourLeftEvent $event) use ($tripWithPassengers, $passengerA) {
            return $event->trip->id === $tripWithPassengers->id && $event->to->id === $passengerA->id;
        });
        Event::assertDispatched(HourLeftEvent::class, function (HourLeftEvent $event) use ($tripWithPassengers, $passengerB) {
            return $event->trip->id === $tripWithPassengers->id && $event->to->id === $passengerB->id;
        });
        Event::assertDispatchedTimes(HourLeftEvent::class, 3);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $e): bool {
            return $e->level === 'info' && $e->message === 'COMMAND TripRemainder';
        });
    }

    public function test_handle_dispatches_hour_left_when_exactly_one_accepted_passenger(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 9, 25, 0));
        Event::fake([HourLeftEvent::class, MessageLogged::class]);

        $driver = (object) ['id' => 10];
        $passengerA = (object) ['id' => 20];

        $tripWithOnePassenger = (object) [
            'id' => 5,
            'user' => $driver,
            'passengerAccepted' => collect([
                (object) ['user' => $passengerA],
            ]),
        ];

        $repo = Mockery::mock(TripRepository::class);
        $repo->shouldReceive('index')
            ->once()
            ->andReturn(new Collection([$tripWithOnePassenger]));

        $command = new TripRemainder(Mockery::mock(TripsManager::class), $repo);
        $command->handle();

        Event::assertDispatchedTimes(HourLeftEvent::class, 2);
    }

    public function test_command_contract_is_defined(): void
    {
        $command = new TripRemainder(Mockery::mock(TripsManager::class), Mockery::mock(TripRepository::class));

        $this->assertSame('trip:remainder', $command->getName());
        $this->assertStringContainsString('Create rates from ending trips', $command->getDescription());
    }
}
