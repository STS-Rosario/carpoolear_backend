<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Mockery;
use STS\Console\Commands\CreateRates;
use STS\Services\Logic\RatingManager;
use Tests\TestCase;

class CreateRatesTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_calls_active_ratings_with_previous_day_timestamp(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 9, 0, 0));
        Event::fake([MessageLogged::class]);

        $ratingManager = Mockery::mock(RatingManager::class);
        $ratingManager->shouldReceive('activeRatings')
            ->once()
            ->with('2026-04-27 09:00:00');

        $command = new CreateRates($ratingManager);
        $command->handle();

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $e): bool {
            return $e->level === 'info' && $e->message === 'COMMAND CreateRates';
        });
    }

    public function test_command_contract_is_defined(): void
    {
        $command = new CreateRates(Mockery::mock(RatingManager::class));

        $this->assertSame('rate:create', $command->getName());
        $this->assertStringContainsString('Create rates from ending trips', $command->getDescription());
    }
}
