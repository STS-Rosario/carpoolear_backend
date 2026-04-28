<?php

namespace Tests\Unit\Services\Logic;

use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use STS\Models\User;
use STS\Notifications\DummyNotification;
use STS\Repository\NotificationRepository;
use STS\Services\Logic\NotificationManager;
use Tests\TestCase;

class NotificationManagerTest extends TestCase
{
    private function manager(): NotificationManager
    {
        return new NotificationManager(new NotificationRepository);
    }

    private function sendDummy(User $user, string $dummyValue): void
    {
        Mail::fake();

        $dummy = new DummyNotification;
        $dummy->setAttribute('dummy', $dummyValue);
        $dummy->notify($user);
    }

    public function test_get_notifications_returns_expected_shape(): void
    {
        Carbon::setTestNow('2026-04-01 12:00:00');
        $user = User::factory()->create();
        $this->sendDummy($user, 'alpha');

        $rows = $this->manager()->getNotifications($user, []);

        $this->assertCount(1, $rows);
        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertArrayHasKey('readed', $rows[0]);
        $this->assertArrayHasKey('created_at', $rows[0]);
        $this->assertArrayHasKey('text', $rows[0]);
        $this->assertArrayHasKey('extras', $rows[0]);
        $this->assertSame('Dummy Notification alpha', $rows[0]['text']);
        $this->assertFalse($rows[0]['readed']);
        $this->assertSame([], $rows[0]['extras']);

        Carbon::setTestNow();
    }

    public function test_get_notifications_with_mark_marks_every_notification(): void
    {
        Carbon::setTestNow('2026-04-02 10:00:00');
        $user = User::factory()->create();
        $this->sendDummy($user, 'one');
        Carbon::setTestNow('2026-04-02 11:00:00');
        $this->sendDummy($user, 'two');

        $manager = $this->manager();
        $this->assertSame(2, $manager->getUnreadCount($user));

        $manager->getNotifications($user, ['mark' => 'true']);

        $this->assertSame(0, $manager->getUnreadCount($user));

        Carbon::setTestNow();
    }

    public function test_get_notifications_paginates_when_page_and_page_size_present(): void
    {
        Carbon::setTestNow('2026-05-01 09:00:00');
        $user = User::factory()->create();
        $this->sendDummy($user, 'old');
        Carbon::setTestNow('2026-05-02 09:00:00');
        $this->sendDummy($user, 'new');

        $firstPage = $this->manager()->getNotifications($user, [
            'page' => '1',
            'page_size' => '1',
        ]);
        $this->assertCount(1, $firstPage);
        $this->assertSame('Dummy Notification new', $firstPage[0]['text']);

        $secondPage = $this->manager()->getNotifications($user, [
            'page' => '2',
            'page_size' => '1',
        ]);
        $this->assertCount(1, $secondPage);
        $this->assertSame('Dummy Notification old', $secondPage[0]['text']);

        Carbon::setTestNow();
    }

    public function test_get_notifications_does_not_paginate_when_page_size_is_missing(): void
    {
        Carbon::setTestNow('2026-05-10 09:00:00');
        $user = User::factory()->create();
        $this->sendDummy($user, 'first');
        Carbon::setTestNow('2026-05-11 09:00:00');
        $this->sendDummy($user, 'second');

        $rows = $this->manager()->getNotifications($user, [
            'page' => '1',
        ]);

        $this->assertCount(2, $rows);
        $this->assertSame('Dummy Notification second', $rows[0]['text']);
        $this->assertSame('Dummy Notification first', $rows[1]['text']);

        Carbon::setTestNow();
    }

    public function test_get_notifications_does_not_paginate_when_page_is_missing(): void
    {
        Carbon::setTestNow('2026-05-12 09:00:00');
        $user = User::factory()->create();
        $this->sendDummy($user, 'first');
        Carbon::setTestNow('2026-05-13 09:00:00');
        $this->sendDummy($user, 'second');

        $rows = $this->manager()->getNotifications($user, [
            'page_size' => '1',
        ]);

        $this->assertCount(2, $rows);
        $this->assertSame('Dummy Notification second', $rows[0]['text']);
        $this->assertSame('Dummy Notification first', $rows[1]['text']);

        Carbon::setTestNow();
    }

    public function test_get_unread_count_ignores_read_notifications(): void
    {
        Carbon::setTestNow('2026-06-01 08:00:00');
        $user = User::factory()->create();
        $this->sendDummy($user, 'a');

        $manager = $this->manager();
        $this->assertSame(1, $manager->getUnreadCount($user));

        $manager->getNotifications($user, ['mark' => true]);
        $this->assertSame(0, $manager->getUnreadCount($user));

        Carbon::setTestNow();
    }

    public function test_get_notifications_with_mark_false_does_not_mark_as_read(): void
    {
        Carbon::setTestNow('2026-06-02 08:00:00');
        $user = User::factory()->create();
        $this->sendDummy($user, 'a');

        $manager = $this->manager();
        $rows = $manager->getNotifications($user, ['mark' => 'false']);

        $this->assertCount(1, $rows);
        $this->assertFalse($rows[0]['readed']);
        $this->assertSame(1, $manager->getUnreadCount($user));

        Carbon::setTestNow();
    }

    public function test_delete_soft_deletes_notification_owned_by_user(): void
    {
        Carbon::setTestNow('2026-07-01 10:00:00');
        $user = User::factory()->create();
        $this->sendDummy($user, 'x');

        $rows = $this->manager()->getNotifications($user, []);
        $id = (int) $rows[0]['id'];

        $this->manager()->delete($user, $id);

        $after = $this->manager()->getNotifications($user, []);
        $this->assertCount(0, $after);

        Carbon::setTestNow();
    }

    public function test_delete_does_not_affect_other_users_notification(): void
    {
        Carbon::setTestNow('2026-08-01 10:00:00');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->sendDummy($owner, 'mine');

        $ownerRows = $this->manager()->getNotifications($owner, []);
        $id = (int) $ownerRows[0]['id'];

        $result = $this->manager()->delete($other, $id);

        $this->assertNull($result);
        $this->assertCount(1, $this->manager()->getNotifications($owner, []));

        Carbon::setTestNow();
    }

    public function test_delete_returns_null_when_notification_does_not_exist(): void
    {
        $user = User::factory()->create();

        $result = $this->manager()->delete($user, 999999);

        $this->assertNull($result);
    }
}
