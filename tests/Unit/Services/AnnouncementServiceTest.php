<?php

namespace Tests\Unit\Services;

use Illuminate\Support\Facades\Cache;
use STS\Services\AnnouncementService;
use Tests\TestCase;

class AnnouncementServiceTest extends TestCase
{
    public function test_can_send_announcement_enforces_hourly_limit(): void
    {
        Cache::forget('announcement_rate_limit');
        $service = new AnnouncementService;

        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue($service->canSendAnnouncement());
        }

        $this->assertFalse($service->canSendAnnouncement());
    }
}
