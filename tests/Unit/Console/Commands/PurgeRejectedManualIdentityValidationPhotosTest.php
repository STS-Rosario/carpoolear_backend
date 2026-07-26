<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use STS\Models\ManualIdentityValidation;
use STS\Models\User;
use STS\Notifications\ManualIdentityValidationPhotosPurgedNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\PushChannel;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class PurgeRejectedManualIdentityValidationPhotosTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_handle_purges_photos_for_rejected_requests_older_than_retention_days(): void
    {
        Storage::fake('local');
        Carbon::setTestNow(Carbon::create(2026, 7, 26, 5, 0, 0));
        Config::set('carpoolear.manual_identity_validation_rejected_photo_retention_days', 7);

        $user = User::factory()->create();
        $front = 'identity_validations/1/front.jpg';
        $back = 'identity_validations/1/back.jpg';
        $selfie = 'identity_validations/1/selfie.jpg';
        Storage::disk('local')->put($front, 'front-bytes');
        Storage::disk('local')->put($back, 'back-bytes');
        Storage::disk('local')->put($selfie, 'selfie-bytes');

        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => Carbon::now()->subDays(20),
            'submitted_at' => Carbon::now()->subDays(15),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_REJECTED,
            'reviewed_at' => Carbon::now()->subDays(8),
            'front_image_path' => $front,
            'back_image_path' => $back,
            'selfie_image_path' => $selfie,
        ]);

        $this->artisan('manual-identity-validation:purge-rejected-photos')
            ->expectsOutput('Rejected manual identity validation photos purged: 1')
            ->assertExitCode(0);

        $fresh = $row->fresh();
        $this->assertFalse(Storage::disk('local')->exists($front));
        $this->assertFalse(Storage::disk('local')->exists($back));
        $this->assertFalse(Storage::disk('local')->exists($selfie));
        $this->assertNull($fresh->front_image_path);
        $this->assertNull($fresh->back_image_path);
        $this->assertNull($fresh->selfie_image_path);
        $this->assertNotNull($fresh->images_purged_at);
        $this->assertSame(ManualIdentityValidation::REVIEW_STATUS_REJECTED, $fresh->review_status);
    }

    public function test_handle_does_not_purge_pending_requests_awaiting_admin_review(): void
    {
        Storage::fake('local');
        Carbon::setTestNow(Carbon::create(2026, 7, 26, 5, 0, 0));
        Config::set('carpoolear.manual_identity_validation_rejected_photo_retention_days', 7);

        $user = User::factory()->create();
        $front = 'identity_validations/2/front.jpg';
        Storage::disk('local')->put($front, 'front-bytes');

        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => Carbon::now()->subDays(20),
            'submitted_at' => Carbon::now()->subDays(15),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
            'front_image_path' => $front,
        ]);

        $this->artisan('manual-identity-validation:purge-rejected-photos')
            ->expectsOutput('Rejected manual identity validation photos purged: 0')
            ->assertExitCode(0);

        $this->assertTrue(Storage::disk('local')->exists($front));
        $this->assertSame($front, $row->fresh()->front_image_path);
        $this->assertNull($row->fresh()->images_purged_at);
    }

    public function test_handle_does_not_purge_recently_rejected_requests(): void
    {
        Storage::fake('local');
        Carbon::setTestNow(Carbon::create(2026, 7, 26, 5, 0, 0));
        Config::set('carpoolear.manual_identity_validation_rejected_photo_retention_days', 7);

        $user = User::factory()->create();
        $front = 'identity_validations/3/front.jpg';
        Storage::disk('local')->put($front, 'front-bytes');

        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => Carbon::now()->subDays(10),
            'submitted_at' => Carbon::now()->subDays(5),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_REJECTED,
            'reviewed_at' => Carbon::now()->subDays(3),
            'front_image_path' => $front,
        ]);

        $this->artisan('manual-identity-validation:purge-rejected-photos')
            ->expectsOutput('Rejected manual identity validation photos purged: 0')
            ->assertExitCode(0);

        $this->assertTrue(Storage::disk('local')->exists($front));
        $this->assertSame($front, $row->fresh()->front_image_path);
        $this->assertNull($row->fresh()->images_purged_at);
    }

    public function test_handle_does_not_purge_already_purged_requests(): void
    {
        Storage::fake('local');
        Carbon::setTestNow(Carbon::create(2026, 7, 26, 5, 0, 0));
        Config::set('carpoolear.manual_identity_validation_rejected_photo_retention_days', 7);

        $user = User::factory()->create();
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => Carbon::now()->subDays(20),
            'submitted_at' => Carbon::now()->subDays(15),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_REJECTED,
            'reviewed_at' => Carbon::now()->subDays(10),
            'images_purged_at' => Carbon::now()->subDays(2),
        ]);

        $this->artisan('manual-identity-validation:purge-rejected-photos')
            ->expectsOutput('Rejected manual identity validation photos purged: 0')
            ->assertExitCode(0);

        $this->assertSame(
            '2026-07-24 05:00:00',
            $row->fresh()->images_purged_at->format('Y-m-d H:i:s')
        );
    }

    public function test_handle_notifies_user_when_photos_are_purged(): void
    {
        Storage::fake('local');
        Carbon::setTestNow(Carbon::create(2026, 7, 26, 5, 0, 0));
        Config::set('carpoolear.manual_identity_validation_rejected_photo_retention_days', 7);

        $user = User::factory()->create();
        $front = 'identity_validations/4/front.jpg';
        Storage::disk('local')->put($front, 'front-bytes');

        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => Carbon::now()->subDays(20),
            'submitted_at' => Carbon::now()->subDays(15),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_REJECTED,
            'reviewed_at' => Carbon::now()->subDays(8),
            'front_image_path' => $front,
        ]);

        $this->mock(NotificationServices::class, function ($mock) use ($user, $row) {
            $mock->shouldReceive('send')
                ->twice()
                ->withArgs(function ($notification, $recipient, $channel) use ($user, $row) {
                    if (! $notification instanceof ManualIdentityValidationPhotosPurgedNotification) {
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

        $this->artisan('manual-identity-validation:purge-rejected-photos')
            ->assertExitCode(0);
    }
}
