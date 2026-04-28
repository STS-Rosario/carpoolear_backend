<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Mockery;
use STS\Console\Commands\BuildRoutes;
use STS\Events\Trip\Create as TripCreateEvent;
use STS\Models\Route;
use STS\Models\Trip;
use STS\Models\User;
use STS\Services\Logic\RoutesManager;
use Tests\TestCase;

class BuildRoutesTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_processes_first_unprocessed_route_and_dispatches_trip_create_events(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 10, 0, 0));
        Event::fake();

        $driver = User::factory()->create();
        $route = Route::query()->create([
            'from_id' => 1,
            'to_id' => 2,
            'processed' => false,
        ]);

        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDay(),
        ]);
        $trip->routes()->attach($route->id);

        $logic = Mockery::mock(RoutesManager::class);
        $logic->shouldReceive('createRoute')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg instanceof Route && $arg->id === $route->id));

        $command = new BuildRoutes($logic);
        $command->handle();

        Event::assertDispatched(TripCreateEvent::class, function (TripCreateEvent $event) use ($trip) {
            return $event->trip->id === $trip->id;
        });
        Event::assertDispatchedTimes(TripCreateEvent::class, 1);
    }

    public function test_command_contract_is_defined(): void
    {
        $command = new BuildRoutes(Mockery::mock(RoutesManager::class));

        $this->assertSame('georoute:build', $command->getName());
        $this->assertStringContainsString('Create geo node route for trips', $command->getDescription());
    }
}
