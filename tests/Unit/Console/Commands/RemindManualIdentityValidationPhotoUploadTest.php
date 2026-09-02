<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use STS\Models\ManualIdentityValidation;
use STS\Models\User;
use STS\Notifications\ManualIdentityValidationUploadReminderNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\PushChannel;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class RemindManualIdentityValidationPhotoUploadTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPaidAwaitingPhotos(User $user, array $overrides = []): ManualIdentityValidation
    {
        return ManualIdentityValidation::create(array_merge([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => Carbon::now()->subDays(8),
            'submitted_at' => null,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_AWAITING_PHOTOS,
        ], $overrides));
    }

    public function test_handle_sends_week1_reminder_seven_days_after_payment_when_photos_are_missing(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 2, 10, 0, 0));
        $user = User::factory()->create();
        $row = $this->createPaidAwaitingPhotos($user, [
            'paid_at' => Carbon::now()->subDays(7),
        ]);

        $this->expectReminderSentTo($user, $row, 1);

        $this->artisan('manual-identity-validation:remind-upload-photos')
            ->expectsOutput('Manual identity validation photo upload reminders sent: 1')
            ->assertExitCode(0);

        $fresh = $row->fresh();
        $this->assertNotNull($fresh->photos_upload_reminder_week1_sent_at);
        $this->assertNull($fresh->photos_upload_reminder_week2_sent_at);
    }

    public function test_handle_does_not_send_week1_reminder_before_seven_days(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 2, 10, 0, 0));
        $user = User::factory()->create();
        $row = $this->createPaidAwaitingPhotos($user, [
            'paid_at' => Carbon::now()->subDays(6),
        ]);

        $this->mock(NotificationServices::class, function ($mock) {
            $mock->shouldReceive('send')->never();
        });

        $this->artisan('manual-identity-validation:remind-upload-photos')
            ->expectsOutput('Manual identity validation photo upload reminders sent: 0')
            ->assertExitCode(0);

        $this->assertNull($row->fresh()->photos_upload_reminder_week1_sent_at);
    }

    public function test_handle_does_not_send_when_photos_were_already_submitted(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 2, 10, 0, 0));
        $user = User::factory()->create();
        $row = $this->createPaidAwaitingPhotos($user, [
            'paid_at' => Carbon::now()->subDays(10),
            'submitted_at' => Carbon::now()->subDays(1),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->mock(NotificationServices::class, function ($mock) {
            $mock->shouldReceive('send')->never();
        });

        $this->artisan('manual-identity-validation:remind-upload-photos')
            ->expectsOutput('Manual identity validation photo upload reminders sent: 0')
            ->assertExitCode(0);

        $this->assertNull($row->fresh()->photos_upload_reminder_week1_sent_at);
    }

    public function test_handle_does_not_resend_week1_reminder(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 2, 10, 0, 0));
        $user = User::factory()->create();
        $row = $this->createPaidAwaitingPhotos($user, [
            'paid_at' => Carbon::now()->subDays(10),
            'photos_upload_reminder_week1_sent_at' => Carbon::now()->subDays(3),
        ]);

        $this->mock(NotificationServices::class, function ($mock) {
            $mock->shouldReceive('send')->never();
        });

        $this->artisan('manual-identity-validation:remind-upload-photos')
            ->expectsOutput('Manual identity validation photo upload reminders sent: 0')
            ->assertExitCode(0);

        $this->assertSame(
            '2026-08-30 10:00:00',
            $row->fresh()->photos_upload_reminder_week1_sent_at->format('Y-m-d H:i:s')
        );
    }

    public function test_handle_sends_week2_reminder_fourteen_days_after_payment_when_photos_are_missing(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 2, 10, 0, 0));
        $user = User::factory()->create();
        $row = $this->createPaidAwaitingPhotos($user, [
            'paid_at' => Carbon::now()->subDays(14),
            'photos_upload_reminder_week1_sent_at' => Carbon::now()->subDays(7),
        ]);

        $this->expectReminderSentTo($user, $row, 1);

        $this->artisan('manual-identity-validation:remind-upload-photos')
            ->expectsOutput('Manual identity validation photo upload reminders sent: 1')
            ->assertExitCode(0);

        $fresh = $row->fresh();
        $this->assertNotNull($fresh->photos_upload_reminder_week2_sent_at);
        $this->assertSame(
            '2026-08-26 10:00:00',
            $fresh->photos_upload_reminder_week1_sent_at->format('Y-m-d H:i:s')
        );
    }

    public function test_handle_sends_only_week2_when_both_reminders_are_due(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 2, 10, 0, 0));
        $user = User::factory()->create();
        $row = $this->createPaidAwaitingPhotos($user, [
            'paid_at' => Carbon::now()->subDays(15),
        ]);

        $this->expectReminderSentTo($user, $row, 1);

        $this->artisan('manual-identity-validation:remind-upload-photos')
            ->expectsOutput('Manual identity validation photo upload reminders sent: 1')
            ->assertExitCode(0);

        $fresh = $row->fresh();
        $this->assertNotNull($fresh->photos_upload_reminder_week1_sent_at);
        $this->assertNotNull($fresh->photos_upload_reminder_week2_sent_at);
    }

    public function test_handle_does_not_send_for_unpaid_or_resolved_requests(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 9, 2, 10, 0, 0));
        $user = User::factory()->create();

        $this->createPaidAwaitingPhotos($user, [
            'paid' => false,
            'paid_at' => null,
        ]);
        $this->createPaidAwaitingPhotos($user, [
            'paid_at' => Carbon::now()->subDays(20),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_APPROVED,
        ]);
        $this->createPaidAwaitingPhotos($user, [
            'paid_at' => Carbon::now()->subDays(20),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_REJECTED,
        ]);
        $this->createPaidAwaitingPhotos($user, [
            'paid_at' => Carbon::now()->subDays(20),
            'review_status' => 'closed',
        ]);

        $this->mock(NotificationServices::class, function ($mock) {
            $mock->shouldReceive('send')->never();
        });

        $this->artisan('manual-identity-validation:remind-upload-photos')
            ->expectsOutput('Manual identity validation photo upload reminders sent: 0')
            ->assertExitCode(0);
    }

    private function expectReminderSentTo(User $user, ManualIdentityValidation $row, int $times): void
    {
        $this->mock(NotificationServices::class, function ($mock) use ($user, $row, $times) {
            $mock->shouldReceive('send')
                ->times($times * 2)
                ->withArgs(function ($notification, $recipient, $channel) use ($user, $row) {
                    if (! $notification instanceof ManualIdentityValidationUploadReminderNotification) {
                        return false;
                    }
                    if ((int) $notification->getAttribute('request_id') !== (int) $row->id) {
                        return false;
                    }
                    if (! $recipient instanceof User || (int) $recipient->id !== (int) $user->id) {
                        return false;
                    }

                    return in_array($channel, [
                        DatabaseChannel::class,
                        PushChannel::class,
                    ], true);
                });
        });
    }
}
