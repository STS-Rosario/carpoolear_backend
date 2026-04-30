<?php

namespace Tests\Unit\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use STS\Models\Device;
use STS\Models\User;
use STS\Services\AnnouncementService;
use Tests\TestCase;

class AnnouncementServiceTest extends TestCase
{
    public function test_send_announcement_passes_default_options_to_send_to_user(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'banned' => false,
        ]);

        $service = new class extends AnnouncementService
        {
            public array $capturedOptions = [];

            public function sendToUser($user, $message, $options = [])
            {
                $this->capturedOptions = $options;

                return ['success' => true, 'skipped' => false];
            }
        };

        $result = $service->sendAnnouncement('Hello world');

        $this->assertTrue($result['success']);
        $this->assertSame('Announcement completed', $result['message']);
        $this->assertSame(1, $result['stats']['total']);
        $this->assertSame(1, $result['stats']['processed']);
        $this->assertSame(1, $result['stats']['successful']);
        $this->assertSame(0, $result['stats']['failed']);
        $this->assertSame(0, $result['stats']['skipped']);
        $this->assertSame('Carpoolear', $service->capturedOptions['title']);
        $this->assertNull($service->capturedOptions['external_url']);
        $this->assertSame(100, $service->capturedOptions['batch_size']);
        $this->assertSame(1, $service->capturedOptions['delay_between_batches']);
        $this->assertSame(0.1, $service->capturedOptions['delay_between_users']);
        $this->assertFalse($service->capturedOptions['active_only']);
        $this->assertSame(0, $service->capturedOptions['device_activity_days']);
        $this->assertSame(3, $service->capturedOptions['max_retries']);
        $this->assertSame(1000, $service->capturedOptions['rate_limit_per_minute']);
    }

    public function test_send_announcement_with_active_only_excludes_users_without_recent_connection(): void
    {
        User::factory()->create([
            'active' => true,
            'banned' => false,
            'last_connection' => Carbon::now()->subDays(45),
        ]);
        User::factory()->create([
            'active' => true,
            'banned' => false,
            'last_connection' => Carbon::now()->subDays(5),
        ]);

        $service = new class extends AnnouncementService
        {
            public int $processedUsers = 0;

            public function sendToUser($user, $message, $options = [])
            {
                $this->processedUsers++;

                return ['success' => true, 'skipped' => false];
            }
        };

        $result = $service->sendAnnouncement('Hello world', ['active_only' => true]);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['stats']['total']);
        $this->assertSame(1, $result['stats']['processed']);
        $this->assertSame(1, $service->processedUsers);
    }

    public function test_send_announcement_returns_no_users_found_when_there_are_no_eligible_users(): void
    {
        $service = new AnnouncementService;
        $result = $service->sendAnnouncement('Hello world');

        $this->assertFalse($result['success']);
        $this->assertSame('No users found matching the criteria', $result['message']);
        $this->assertSame(0, $result['stats']['total']);
        $this->assertSame(0, $result['stats']['successful']);
    }

    public function test_can_send_announcement_enforces_hourly_limit(): void
    {
        Cache::forget('announcement_rate_limit');
        $service = new AnnouncementService;

        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue($service->canSendAnnouncement());
        }

        $this->assertFalse($service->canSendAnnouncement());
    }

    public function test_send_to_users_returns_error_when_ids_are_invalid(): void
    {
        $service = new AnnouncementService;
        $result = $service->sendToUsers(' , ,not-a-number,0', 'Hello world');

        $this->assertFalse($result['success']);
        $this->assertSame('No valid user IDs provided', $result['message']);
        $this->assertSame(0, $result['stats']['total']);
        $this->assertSame(0, $result['stats']['processed']);
    }

    public function test_send_to_users_returns_error_when_ids_array_is_empty(): void
    {
        $service = new AnnouncementService;
        $result = $service->sendToUsers([], 'Hello world');

        $this->assertFalse($result['success']);
        $this->assertSame('No valid user IDs provided', $result['message']);
        $this->assertSame(0, $result['stats']['total']);
        $this->assertSame(0, $result['stats']['found']);
    }

    public function test_can_send_announcement_returns_false_when_limit_is_already_reached(): void
    {
        Cache::put('announcement_rate_limit', 10, 3600);

        $service = new AnnouncementService;

        $this->assertFalse($service->canSendAnnouncement());
        $this->assertSame(10, Cache::get('announcement_rate_limit'));
    }

    public function test_can_send_announcement_increments_existing_counter_below_limit(): void
    {
        Cache::put('announcement_rate_limit', 9, 3600);
        $service = new AnnouncementService;

        $this->assertTrue($service->canSendAnnouncement());
        $this->assertSame(10, Cache::get('announcement_rate_limit'));
    }

    public function test_can_send_announcement_initializes_counter_when_cache_key_is_missing(): void
    {
        Cache::forget('announcement_rate_limit');
        $service = new AnnouncementService;

        $this->assertTrue($service->canSendAnnouncement());
        $this->assertSame(1, Cache::get('announcement_rate_limit'));
    }

    public function test_send_to_users_counts_user_without_devices_as_skipped(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'banned' => false,
        ]);

        $service = new AnnouncementService;
        $result = $service->sendToUsers([$user->id], 'Hello world', [
            'device_activity_days' => 0,
            'title' => 'Carpoolear',
            'external_url' => null,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['stats']['total']);
        $this->assertSame(1, $result['stats']['found']);
        $this->assertSame(1, $result['stats']['processed']);
        $this->assertSame(0, $result['stats']['successful']);
        $this->assertSame(1, $result['stats']['skipped']);
        $this->assertSame(0, $result['stats']['failed']);
    }

    public function test_send_to_user_skips_when_only_stale_devices_exist_and_activity_filter_is_enabled(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'banned' => false,
        ]);
        Device::query()->create([
            'user_id' => $user->id,
            'session_id' => 'sess-announce-'.uniqid('', true),
            'device_id' => 'device-announce-'.uniqid('', true),
            'device_type' => 'ios',
            'app_version' => 1,
            'notifications' => true,
            'last_activity' => Carbon::now()->subDays(40),
        ]);

        $service = new AnnouncementService;
        $result = $service->sendToUser($user, 'Hello world', [
            'title' => 'Carpoolear',
            'external_url' => null,
            'device_activity_days' => 30,
        ]);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['skipped']);
        $this->assertSame('No active devices found', $result['message']);
    }
}
