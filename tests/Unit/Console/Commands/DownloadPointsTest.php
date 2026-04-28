<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use Mockery;
use STS\Console\Commands\DownloadPoints;
use STS\Models\Trip;
use STS\Models\TripPoint;
use STS\Models\User;
use STS\Services\GoogleDirection;
use Tests\TestCase;

class DownloadPointsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_downloads_points_only_for_future_trips_without_points(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 10, 0, 0));

        $user = User::factory()->create();
        $tripWithoutPoints = Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::now()->addDay(),
        ]);
        $tripWithPoints = Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::now()->addDays(2),
        ]);

        TripPoint::factory()->create([
            'trip_id' => $tripWithPoints->id,
            'address' => 'Point',
            'json_address' => ['name' => 'Point'],
            'lat' => -34.6,
            'lng' => -58.4,
        ]);

        $downloader = Mockery::mock(GoogleDirection::class);
        $downloader->shouldReceive('download')
            ->once()
            ->with(Mockery::on(fn ($trip) => $trip instanceof Trip && $trip->id === $tripWithoutPoints->id));

        $command = new DownloadPoints;
        $command->download = $downloader;

        $command->handle();
        $this->addToAssertionCount(1);
    }

    public function test_command_contract_is_defined(): void
    {
        $command = new DownloadPoints;

        $this->assertSame('trip:download', $command->getName());
        $this->assertStringContainsString('Download points', $command->getDescription());
    }
}
