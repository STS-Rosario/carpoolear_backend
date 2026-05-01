<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Mockery;
use STS\Console\Commands\GenerateTripVisibility;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\TripRepository;
use Tests\TestCase;

class GenerateTripVisibilityTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_generates_visibility_only_for_future_non_public_trips(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 10, 0, 0));

        $owner = User::factory()->create();
        $eligibleTrip = Trip::factory()->create([
            'user_id' => $owner->id,
            'trip_date' => Carbon::now()->addDay(),
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
        ]);
        Trip::factory()->create([
            'user_id' => $owner->id,
            'trip_date' => Carbon::now()->addDay(),
            'friendship_type_id' => Trip::PRIVACY_PUBLIC,
        ]);
        Trip::factory()->create([
            'user_id' => $owner->id,
            'trip_date' => Carbon::now()->subDay(),
            'friendship_type_id' => Trip::PRIVACY_FRIENDS,
        ]);

        $repo = Mockery::mock(TripRepository::class);
        $repo->shouldReceive('generateTripFriendVisibility')
            ->once()
            ->with(Mockery::on(fn ($trip) => $trip instanceof Trip && $trip->id === $eligibleTrip->id));
        $this->app->instance(TripRepository::class, $repo);

        Event::fake([MessageLogged::class]);

        $this->artisan('trip:visibility')->assertExitCode(0);

        Event::assertDispatched(MessageLogged::class, fn (MessageLogged $e): bool => $e->message === 'COMMAND GenerateTripVisibility');
    }

    public function test_command_contract_is_defined(): void
    {
        $command = new GenerateTripVisibility(Mockery::mock(TripRepository::class));

        $this->assertSame('trip:visibility', $command->getName());
        $this->assertStringContainsString('Generate trip visibility', $command->getDescription());
    }
}
