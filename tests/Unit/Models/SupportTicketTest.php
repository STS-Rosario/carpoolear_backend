<?php

namespace Tests\Unit\Models;

use Carbon\CarbonInterface;
use STS\Models\SupportTicket;
use STS\Models\SupportTicketAttachment;
use STS\Models\SupportTicketReply;
use STS\Models\User;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    public function test_type_default_priorities_assigns_high_to_account_recovery(): void
    {
        $this->assertSame('high', SupportTicket::TYPE_DEFAULT_PRIORITIES['account_recovery']);
    }

    public function test_statuses_include_needs_review(): void
    {
        $this->assertContains(SupportTicket::STATUS_NEEDS_REVIEW, SupportTicket::STATUSES);
    }

    public function test_scope_admin_needs_attention_includes_unread_and_actionable_statuses(): void
    {
        $user = User::factory()->create();
        $withUnread = $this->makeTicket($user, [
            'type' => 'feedback',
            'status' => 'Esperando respuesta',
            'unread_for_admin' => 1,
        ]);
        $needsReview = $this->makeTicket($user, [
            'type' => 'feedback',
            'status' => SupportTicket::STATUS_NEEDS_REVIEW,
            'unread_for_admin' => 0,
        ]);
        $enRevision = $this->makeTicket($user, [
            'type' => 'feedback',
            'status' => 'En revision',
            'unread_for_admin' => 0,
        ]);
        $open = $this->makeTicket($user, [
            'type' => 'feedback',
            'status' => 'Open',
            'unread_for_admin' => 0,
        ]);
        $waitingUser = $this->makeTicket($user, [
            'type' => 'feedback',
            'status' => 'Esperando respuesta',
            'unread_for_admin' => 0,
        ]);
        $resolved = $this->makeTicket($user, [
            'type' => 'feedback',
            'status' => 'Resuelto',
            'unread_for_admin' => 0,
        ]);

        $ids = SupportTicket::query()->adminNeedsAttention()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($withUnread->id, $ids);
        $this->assertContains($needsReview->id, $ids);
        $this->assertContains($enRevision->id, $ids);
        $this->assertContains($open->id, $ids);
        $this->assertNotContains($waitingUser->id, $ids);
        $this->assertNotContains($resolved->id, $ids);
    }

    public function test_scope_admin_needs_attention_excludes_closed_and_resolved_even_with_unread(): void
    {
        $user = User::factory()->create();
        $resolvedUnread = $this->makeTicket($user, [
            'type' => 'feedback',
            'status' => 'Resuelto',
            'unread_for_admin' => 1,
        ]);
        $closedUnread = $this->makeTicket($user, [
            'type' => 'feedback',
            'status' => 'Cerrado',
            'unread_for_admin' => 1,
        ]);

        $ids = SupportTicket::query()->adminNeedsAttention()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertNotContains($resolvedUnread->id, $ids);
        $this->assertNotContains($closedUnread->id, $ids);
    }

    public function test_fillable_contains_expected_mass_assignable_attributes(): void
    {
        $this->assertSame([
            'user_id',
            'type',
            'subject',
            'status',
            'priority',
            'unread_for_user',
            'unread_for_admin',
            'internal_note_markdown',
            'last_reply_at',
            'created_by',
            'updated_by',
            'closed_by',
            'closed_at',
            'assigned_to_user_id',
            'assigned_at',
        ], (new SupportTicket)->getFillable());
    }

    public function test_assigned_to_relation_resolves_user(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $ticket = $this->makeTicket($owner, [
            'assigned_to_user_id' => $admin->id,
            'assigned_at' => '2026-07-19 12:00:00',
        ]);

        $ticket = $ticket->fresh(['assignedTo']);

        $this->assertTrue($ticket->assignedTo->is($admin));
        $this->assertInstanceOf(CarbonInterface::class, $ticket->assigned_at);
    }

    private function makeTicket(User $user, array $overrides = []): SupportTicket
    {
        return SupportTicket::query()->create(array_merge([
            'user_id' => $user->id,
            'type' => 'general',
            'subject' => 'Need help',
            'status' => 'Open',
            'priority' => 'normal',
            'unread_for_user' => 0,
            'unread_for_admin' => 0,
        ], $overrides));
    }

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $ticket = $this->makeTicket($user);

        $this->assertTrue($ticket->user->is($user));
    }

    public function test_last_reply_at_and_closed_at_cast_to_datetime(): void
    {
        $user = User::factory()->create();
        $ticket = $this->makeTicket($user, [
            'last_reply_at' => '2026-03-10 14:30:00',
            'closed_at' => '2026-03-11 09:00:00',
        ]);

        $ticket = $ticket->fresh();
        $this->assertInstanceOf(CarbonInterface::class, $ticket->last_reply_at);
        $this->assertInstanceOf(CarbonInterface::class, $ticket->closed_at);
        $this->assertSame('2026-03-10', $ticket->last_reply_at->toDateString());
        $this->assertSame('2026-03-11', $ticket->closed_at->toDateString());
    }

    public function test_replies_relation(): void
    {
        $user = User::factory()->create();
        $ticket = $this->makeTicket($user);

        SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'is_admin' => false,
            'message_markdown' => 'First message',
        ]);

        $this->assertSame(1, $ticket->fresh()->replies()->count());
        $this->assertSame('First message', $ticket->replies()->first()->message_markdown);
    }

    public function test_attachments_relation_for_ticket_level_files(): void
    {
        $user = User::factory()->create();
        $ticket = $this->makeTicket($user);

        SupportTicketAttachment::query()->create([
            'ticket_id' => $ticket->id,
            'reply_id' => null,
            'user_id' => $user->id,
            'path' => 'tickets/1/file.bin',
            'original_name' => 'file.bin',
            'mime' => 'application/octet-stream',
            'size_bytes' => 42,
        ]);

        $this->assertSame(1, $ticket->fresh()->attachments()->count());
        $this->assertSame('file.bin', $ticket->attachments()->first()->original_name);
    }

    public function test_count_for_user_returns_zero_when_user_id_is_null(): void
    {
        $this->assertSame(0, SupportTicket::countForUser(null));
    }

    public function test_count_for_user_counts_all_tickets_for_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->makeTicket($user, ['subject' => 'One']);
        $this->makeTicket($user, ['subject' => 'Two']);
        $this->makeTicket($other, ['subject' => 'Other']);

        $this->assertSame(2, SupportTicket::countForUser($user->id));
    }
}
