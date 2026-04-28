<?php

namespace Tests\Unit\Services;

use Illuminate\Support\Facades\Cache;
use STS\Models\User;
use STS\Services\AnnouncementService;
use Tests\TestCase;

class AnnouncementServiceTest extends TestCase
{
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
}
