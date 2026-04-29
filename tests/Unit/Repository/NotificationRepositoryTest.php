<?php

namespace Tests\Unit\Repository;

use Carbon\Carbon;
use STS\Models\User;
use STS\Repository\NotificationRepository;
use STS\Services\Notifications\BaseNotification;
use STS\Services\Notifications\Models\DatabaseNotification;
use Tests\TestCase;

class NotificationRepositoryTest extends TestCase
{
    private function insert_notification(User $user, ?string $readAt, string $createdAt): DatabaseNotification
    {
        $id = DatabaseNotification::query()->insertGetId([
            'user_id' => $user->id,
            'type' => BaseNotification::class,
            'read_at' => $readAt,
            'deleted_at' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return DatabaseNotification::query()->findOrFail($id);
    }

    public function test_get_notifications_returns_non_deleted_ordered_newest_first(): void
    {
        $user = User::factory()->create();
        $repo = new NotificationRepository;

        $oldest = $this->insert_notification($user, null, '2026-01-01 10:00:00');
        $newest = $this->insert_notification($user, null, '2026-03-01 10:00:00');
        $middle = $this->insert_notification($user, null, '2026-02-01 10:00:00');

        $list = $repo->getNotifications($user, false);
        $this->assertCount(3, $list);
        $this->assertTrue($list->first()->is($newest));
        $this->assertTrue($list->last()->is($oldest));
        $this->assertTrue($list->get(1)->is($middle));
    }

    public function test_get_notifications_unread_only_includes_null_read_at(): void
    {
        $user = User::factory()->create();
        $repo = new NotificationRepository;

        $this->insert_notification($user, '2026-04-01 12:00:00', '2026-04-01 09:00:00');
        $unread = $this->insert_notification($user, null, '2026-04-02 09:00:00');

        $list = $repo->getNotifications($user, true);
        $this->assertCount(1, $list);
        $this->assertTrue($list->first()->is($unread));
    }

    public function test_get_notifications_default_argument_uses_all_notifications_not_unread_only(): void
    {
        // Mutation intent: preserve default `$unread = false` in getNotifications signature.
        $user = User::factory()->create();
        $repo = new NotificationRepository;

        $this->insert_notification($user, '2026-04-01 12:00:00', '2026-04-01 09:00:00');
        $this->insert_notification($user, null, '2026-04-02 09:00:00');

        $list = $repo->getNotifications($user);
        $this->assertCount(2, $list);
    }

    public function test_get_notifications_with_page_size_and_page_applies_skip_take(): void
    {
        $user = User::factory()->create();
        $repo = new NotificationRepository;

        $this->insert_notification($user, null, '2026-05-03 10:00:00');
        $this->insert_notification($user, null, '2026-05-02 10:00:00');
        $this->insert_notification($user, null, '2026-05-01 10:00:00');

        $page = $repo->getNotifications($user, false, 1, 2);
        $this->assertCount(1, $page);
        $this->assertSame('2026-05-02 10:00:00', $page->first()->created_at->format('Y-m-d H:i:s'));
    }

    public function test_get_notifications_does_not_paginate_when_only_page_size_or_page_is_provided(): void
    {
        // Mutation intent: keep `$page_size && $page`; OR would incorrectly paginate with only one value.
        $user = User::factory()->create();
        $repo = new NotificationRepository;

        $this->insert_notification($user, null, '2026-05-03 10:00:00');
        $this->insert_notification($user, null, '2026-05-02 10:00:00');
        $this->insert_notification($user, null, '2026-05-01 10:00:00');

        $onlySize = $repo->getNotifications($user, false, 1, null);
        $onlyPage = $repo->getNotifications($user, false, null, 2);

        $this->assertCount(3, $onlySize);
        $this->assertCount(3, $onlyPage);
    }

    public function test_mark_as_read_sets_read_at_on_notification(): void
    {
        Carbon::setTestNow('2026-06-20 15:00:00');
        $user = User::factory()->create();
        $repo = new NotificationRepository;
        $n = $this->insert_notification($user, null, '2026-06-01 10:00:00');

        $repo->markAsRead($n);

        $n = $n->fresh();
        $this->assertNotNull($n->read_at);
        $this->assertSame('2026-06-20 15:00:00', Carbon::parse($n->read_at)->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_delete_sets_deleted_at(): void
    {
        Carbon::setTestNow('2026-07-01 08:00:00');
        $user = User::factory()->create();
        $repo = new NotificationRepository;
        $n = $this->insert_notification($user, null, '2026-06-01 10:00:00');

        $repo->delete($n);

        $n = $n->fresh();
        $this->assertNotNull($n->deleted_at);
        $this->assertSame('2026-07-01 08:00:00', Carbon::parse($n->deleted_at)->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_find_returns_notification_for_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $repo = new NotificationRepository;

        $mine = $this->insert_notification($user, null, '2026-08-01 10:00:00');
        $theirs = $this->insert_notification($other, null, '2026-08-01 11:00:00');

        $found = $repo->find($user, $mine->id);
        $this->assertNotNull($found);
        $this->assertTrue($found->is($mine));

        $this->assertNull($repo->find($user, $theirs->id));
    }
}
