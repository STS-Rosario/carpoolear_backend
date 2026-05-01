<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use Illuminate\Log\Events\MessageLogged;
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
        Event::fake([TripCreateEvent::class, MessageLogged::class]);

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
            ->with(Mockery::on(function ($arg) use ($route): bool {
                return $arg instanceof Route
                    && $arg->id === $route->id
                    && $arg->relationLoaded('origin')
                    && $arg->relationLoaded('destiny');
            }));

        $command = new BuildRoutes($logic);
        $command->handle();

        Event::assertDispatched(MessageLogged::class, fn (MessageLogged $e): bool => $e->message === 'COMMAND BuildRoutes');
        Event::assertDispatched(MessageLogged::class, fn (MessageLogged $e): bool => $e->message === 'Route builder ');

        Event::assertDispatched(TripCreateEvent::class, function (TripCreateEvent $event) use ($trip) {
            return $event->trip->id === $trip->id;
        });
        Event::assertDispatchedTimes(TripCreateEvent::class, 1);
    }

    public function test_handle_only_dispatches_create_event_for_trips_linked_to_the_processed_route(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 10, 0, 0));
        Event::fake([TripCreateEvent::class, MessageLogged::class]);

        $driver = User::factory()->create();
        $routeTarget = Route::query()->create([
            'from_id' => 1,
            'to_id' => 2,
            'processed' => false,
        ]);
        $routeOther = Route::query()->create([
            'from_id' => 2,
            'to_id' => 1,
            'processed' => true,
        ]);

        $tripForTargetRoute = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDay(),
        ]);
        $tripForTargetRoute->routes()->attach($routeTarget->id);

        $tripOtherRouteOnly = Trip::factory()->create([
            'user_id' => $driver->id,
            'trip_date' => Carbon::now()->addDays(2),
        ]);
        $tripOtherRouteOnly->routes()->attach($routeOther->id);

        $logic = Mockery::mock(RoutesManager::class);
        $logic->shouldReceive('createRoute')->once()->with(Mockery::type(Route::class));

        $command = new BuildRoutes($logic);
        $command->handle();

        Event::assertDispatchedTimes(TripCreateEvent::class, 1);
        Event::assertDispatched(TripCreateEvent::class, fn (TripCreateEvent $e): bool => $e->trip->id === $tripForTargetRoute->id);
    }

    public function test_handle_logs_route_builder_when_create_route_throws(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 10, 0, 0));
        Event::fake([TripCreateEvent::class, MessageLogged::class]);

        Route::query()->create([
            'from_id' => 1,
            'to_id' => 2,
            'processed' => false,
        ]);

        $logic = Mockery::mock(RoutesManager::class);
        $logic->shouldReceive('createRoute')->once()->andThrow(new \RuntimeException('route build failed'));

        $command = new BuildRoutes($logic);
        $command->handle();

        Event::assertDispatched(MessageLogged::class, fn (MessageLogged $e): bool => $e->message === 'Route builder ex');
        Event::assertDispatched(MessageLogged::class, fn (MessageLogged $e): bool => str_contains((string) $e->message, 'route build failed'));
        Event::assertNotDispatched(TripCreateEvent::class);
    }

    public function test_command_contract_is_defined(): void
    {
        $command = new BuildRoutes(Mockery::mock(RoutesManager::class));

        $this->assertSame('georoute:build', $command->getName());
        $this->assertStringContainsString('Create geo node route for trips', $command->getDescription());
    }
}
