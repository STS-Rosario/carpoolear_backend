<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use STS\Console\Commands\RequestNotAnswer;
use STS\Events\Trip\Alert\RequestNotAnswer as RequestNotAnswerEvent;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class RequestNotAnswerTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_handle_dispatches_event_for_pending_requests_created_three_days_ago(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 10, 0, 0));
        Event::fake();

        $driver = User::factory()->create();
        $passengerUser = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $pendingThreeDays = Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $passengerUser->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);
        $pendingThreeDays->forceFill([
            'created_at' => Carbon::now()->subDays(3),
            'updated_at' => Carbon::now()->subDays(3),
        ])->saveQuietly();

        $pendingTwoDays = Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => User::factory()->create()->id,
            'request_state' => Passenger::STATE_PENDING,
        ]);
        $pendingTwoDays->forceFill([
            'created_at' => Carbon::now()->subDays(2),
            'updated_at' => Carbon::now()->subDays(2),
        ])->saveQuietly();

        $this->artisan('trip:requestnotanswer')->assertExitCode(0);

        Event::assertDispatched(RequestNotAnswerEvent::class, function (RequestNotAnswerEvent $event) use ($trip, $passengerUser) {
            return $event->trip->id === $trip->id
                && $event->to->id === $passengerUser->id;
        });
        Event::assertDispatchedTimes(RequestNotAnswerEvent::class, 1);
    }

    public function test_command_contract_is_defined(): void
    {
        $command = new RequestNotAnswer;

        $this->assertSame('trip:requestnotanswer', $command->getName());
        $this->assertStringContainsString('Notify not answer request', $command->getDescription());
    }
}
