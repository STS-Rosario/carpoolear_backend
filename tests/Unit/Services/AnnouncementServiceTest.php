<?php

namespace Tests\Unit\Services;

use Illuminate\Support\Facades\Cache;
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
}
