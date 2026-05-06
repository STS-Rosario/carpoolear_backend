<?php

namespace Tests\Unit\Console\Commands;

use Mockery;
use STS\Services\AnnouncementService;
use Tests\TestCase;

class SendAnnouncementTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @return array<string, int>
     */
    private function baseStats(): array
    {
        return [
            'total_users' => 100,
            'active_users' => 80,
            'users_with_devices' => 70,
            'total_devices' => 120,
            'active_devices' => 60,
        ];
    }

    public function test_handle_cancels_when_user_does_not_confirm(): void
    {
        $service = Mockery::mock(AnnouncementService::class);
        $service->shouldReceive('getUserStats')->once()->andReturn($this->baseStats());
        $service->shouldNotReceive('canSendAnnouncement');
        $service->shouldNotReceive('sendAnnouncement');
        $service->shouldNotReceive('sendToUsers');
        $this->app->instance(AnnouncementService::class, $service);

        $this->artisan('announcement:send', ['message' => 'Hola comunidad'])
            ->expectsConfirmation('Do you want to proceed with sending this announcement?', 'no')
            ->expectsOutput('Announcement cancelled.')
            ->assertExitCode(0);
    }

    public function test_handle_dry_run_stops_before_sending(): void
    {
        $service = Mockery::mock(AnnouncementService::class);
        $service->shouldReceive('getUserStats')->once()->andReturn($this->baseStats());
        $service->shouldNotReceive('canSendAnnouncement');
        $service->shouldNotReceive('sendAnnouncement');
        $service->shouldNotReceive('sendToUsers');
        $this->app->instance(AnnouncementService::class, $service);

        $this->artisan('announcement:send', [
            'message' => 'Dry run message',
            '--title' => 'Title',
            '--url' => 'https://example.com',
            '--batch-size' => 50,
            '--dry-run' => true,
            '--active-only' => true,
            '--device-activity-days' => 7,
            '--user-ids' => '1,2,3',
        ])
            ->expectsConfirmation('Do you want to proceed with sending this announcement?', 'yes')
            ->expectsOutput('DRY RUN MODE - No notifications will be sent')
            ->expectsOutput('Would send to specified user IDs: 1,2,3')
            ->assertExitCode(0);
    }

    public function test_handle_sends_to_targeted_users_and_reports_success(): void
    {
        $service = Mockery::mock(AnnouncementService::class);
        $service->shouldReceive('getUserStats')->once()->andReturn($this->baseStats());
        $service->shouldReceive('canSendAnnouncement')->once()->andReturn(true);
        $service->shouldReceive('sendToUsers')
            ->once()
            ->with('10,11', 'Promo', Mockery::on(function ($options) {
                return $options['title'] === 'Big News'
                    && $options['external_url'] === null
                    && $options['batch_size'] === 100
                    && $options['active_only'] === false
                    && $options['device_activity_days'] === 0;
            }))
            ->andReturn([
                'success' => true,
                'stats' => [
                    'total' => 2,
                    'processed' => 2,
                    'successful' => 2,
                    'failed' => 0,
                    'skipped' => 0,
                ],
            ]);
        $service->shouldNotReceive('sendAnnouncement');
        $this->app->instance(AnnouncementService::class, $service);

        $this->artisan('announcement:send', [
            'message' => 'Promo',
            '--title' => 'Big News',
            '--user-ids' => '10,11',
        ])
            ->expectsConfirmation('Do you want to proceed with sending this announcement?', 'yes')
            ->expectsOutputToContain('Announcement completed successfully!')
            ->assertExitCode(0);
    }
}
