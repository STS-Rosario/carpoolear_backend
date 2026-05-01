<?php

namespace Tests\Feature\Commands;

use Mockery;
use Mockery\MockInterface;
use STS\Services\AnnouncementService;
use Tests\TestCase;

class SendAnnouncementCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function baseStats(): array
    {
        return [
            'total_users' => 10,
            'active_users' => 4,
            'users_with_devices' => 7,
            'total_devices' => 9,
            'active_devices' => 3,
        ];
    }

    public function test_prints_banner_configuration_and_statistics_before_confirm(): void
    {
        $this->mock(AnnouncementService::class, function (MockInterface $m): void {
            $m->expects('getUserStats')->once()->andReturn($this->baseStats());
        });

        $this->artisan('announcement:send', [
            'message' => 'Hello riders',
            '--title' => 'System',
            '--url' => 'https://example.org/more',
            '--batch-size' => 50,
            '--dry-run' => true,
            '--active-only' => true,
            '--device-activity-days' => 14,
            '--user-ids' => '12,34',
        ])
            ->expectsConfirmation('Do you want to proceed with sending this announcement?', 'no')
            ->expectsOutputToContain('=== Carpoolear Announcement Tool ===')
            ->expectsOutputToContain('Message: Hello riders')
            ->expectsOutputToContain('Title: System')
            ->expectsOutputToContain('External URL: https://example.org/more')
            ->expectsOutputToContain('Batch Size: 50')
            ->expectsOutputToContain('Dry Run: Yes')
            ->expectsOutputToContain('Active Users Only: Yes')
            ->expectsOutputToContain('Device Activity Days: 14')
            ->expectsOutputToContain('Target User IDs: 12,34')
            ->expectsOutputToContain('=== User Statistics ===')
            ->expectsOutputToContain('Total users: 10')
            ->expectsOutputToContain('Active users (30 days): 4')
            ->expectsOutputToContain('Users with devices: 7')
            ->expectsOutputToContain('Total devices: 9')
            ->expectsOutputToContain('Active devices (30 days): 3')
            ->expectsOutputToContain('Announcement cancelled.')
            ->assertSuccessful();
    }

    public function test_device_activity_days_zero_prints_all_devices_label(): void
    {
        $this->mock(AnnouncementService::class, function (MockInterface $m): void {
            $m->expects('getUserStats')->once()->andReturn($this->baseStats());
        });

        $this->artisan('announcement:send', [
            'message' => 'Ping',
            '--device-activity-days' => 0,
        ])
            ->expectsConfirmation('Do you want to proceed with sending this announcement?', 'no')
            ->expectsOutputToContain('Device Activity Days: All devices')
            ->assertSuccessful();
    }

    public function test_dry_run_after_confirm_shows_targeted_user_message(): void
    {
        $this->mock(AnnouncementService::class, function (MockInterface $m): void {
            $m->expects('getUserStats')->once()->andReturn($this->baseStats());
        });

        $this->artisan('announcement:send', [
            'message' => 'Ping',
            '--dry-run' => true,
            '--user-ids' => '99',
        ])
            ->expectsConfirmation('Do you want to proceed with sending this announcement?', 'yes')
            ->expectsOutputToContain('DRY RUN MODE')
            ->expectsOutputToContain('Would send to specified user IDs: 99')
            ->assertSuccessful();
    }

    public function test_dry_run_without_user_ids_mentions_approximate_recipient_count(): void
    {
        $this->mock(AnnouncementService::class, function (MockInterface $m): void {
            $m->expects('getUserStats')->once()->andReturn($this->baseStats());
        });

        $this->artisan('announcement:send', [
            'message' => 'Ping',
            '--dry-run' => true,
        ])
            ->expectsConfirmation('Do you want to proceed with sending this announcement?', 'yes')
            ->expectsOutputToContain('Would send to approximately 7 users')
            ->assertSuccessful();
    }

    public function test_rate_limit_message_when_service_blocks_send(): void
    {
        $this->mock(AnnouncementService::class, function (MockInterface $m): void {
            $m->expects('getUserStats')->once()->andReturn($this->baseStats());
            $m->expects('canSendAnnouncement')->once()->andReturn(false);
        });

        $this->artisan('announcement:send', ['message' => 'Blocked'])
            ->expectsConfirmation('Do you want to proceed with sending this announcement?', 'yes')
            ->expectsOutputToContain('Rate limit exceeded. Please wait before sending another announcement.')
            ->assertSuccessful();
    }

    public function test_success_path_prints_result_stats_and_success_line(): void
    {
        $this->mock(AnnouncementService::class, function (MockInterface $m): void {
            $m->expects('getUserStats')->once()->andReturn($this->baseStats());
            $m->expects('canSendAnnouncement')->once()->andReturn(true);
            $m->expects('sendAnnouncement')->once()->andReturn([
                'success' => true,
                'stats' => [
                    'total' => 100,
                    'processed' => 100,
                    'successful' => 98,
                    'failed' => 1,
                    'skipped' => 1,
                ],
            ]);
        });

        $this->artisan('announcement:send', ['message' => 'Shipped'])
            ->expectsConfirmation('Do you want to proceed with sending this announcement?', 'yes')
            ->expectsOutputToContain('Sending announcement...')
            ->expectsOutputToContain('=== Announcement Results ===')
            ->expectsOutputToContain('Total users: 100')
            ->expectsOutputToContain('Processed: 100')
            ->expectsOutputToContain('Successful: 98')
            ->expectsOutputToContain('Failed: 1')
            ->expectsOutputToContain('Skipped: 1')
            ->expectsOutputToContain('✓ Announcement completed successfully!')
            ->assertSuccessful();
    }

    public function test_failure_path_prints_error_with_message(): void
    {
        $this->mock(AnnouncementService::class, function (MockInterface $m): void {
            $m->expects('getUserStats')->once()->andReturn($this->baseStats());
            $m->expects('canSendAnnouncement')->once()->andReturn(true);
            $m->expects('sendAnnouncement')->once()->andReturn([
                'success' => false,
                'message' => 'push provider offline',
            ]);
        });

        $this->artisan('announcement:send', ['message' => 'X'])
            ->expectsConfirmation('Do you want to proceed with sending this announcement?', 'yes')
            ->expectsOutputToContain('=== Announcement Results ===')
            ->expectsOutputToContain('✗ Announcement failed: push provider offline')
            ->assertSuccessful();
    }

    public function test_send_to_users_path_uses_service_send_to_users(): void
    {
        $this->mock(AnnouncementService::class, function (MockInterface $m): void {
            $m->expects('getUserStats')->once()->andReturn($this->baseStats());
            $m->expects('canSendAnnouncement')->once()->andReturn(true);
            $m->expects('sendToUsers')
                ->once()
                ->with('1,2', 'Direct', Mockery::type('array'))
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
        });

        $this->artisan('announcement:send', [
            'message' => 'Direct',
            '--user-ids' => '1,2',
        ])
            ->expectsConfirmation('Do you want to proceed with sending this announcement?', 'yes')
            ->expectsOutputToContain('Total users: 2')
            ->assertSuccessful();
    }
}
