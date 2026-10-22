<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use STS\Console\Commands\SupportTicketsAutoClose;
use STS\Models\SupportTicket;
use STS\Models\SupportTicketReply;
use STS\Models\User;
use STS\Notifications\SupportTicketAutoClosedNotification;
use STS\Services\Notifications\Models\DatabaseNotification;
use STS\Support\SupportTicketAutoCloseMessage;
use Tests\TestCase;

class SupportTicketsAutoCloseTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createResolvedTicket(User $user, array $overrides = []): SupportTicket
    {
        return SupportTicket::query()->create(array_merge([
            'user_id' => $user->id,
            'type' => 'general',
            'subject' => 'Ticket '.uniqid('', true),
            'status' => 'Resuelto',
            'priority' => 'normal',
            'unread_for_user' => 0,
            'unread_for_admin' => 0,
            'updated_at' => Carbon::now(),
            'created_at' => Carbon::now(),
        ], $overrides));
    }

    private function createWaitingTicket(User $user, array $overrides = []): SupportTicket
    {
        $ticket = SupportTicket::query()->create(array_merge([
            'user_id' => $user->id,
            'type' => 'general',
            'subject' => 'Ticket '.uniqid('', true),
            'status' => 'Esperando respuesta',
            'priority' => 'normal',
            'unread_for_user' => 0,
            'unread_for_admin' => 0,
            'last_reply_at' => Carbon::now(),
        ], $overrides));

        $ticket->forceFill([
            'created_at' => $overrides['created_at'] ?? Carbon::now(),
            'updated_at' => $overrides['updated_at'] ?? Carbon::now(),
        ])->saveQuietly();

        return $ticket->fresh();
    }

    private function addUserReply(SupportTicket $ticket, User $user, Carbon $at): SupportTicketReply
    {
        $reply = SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'is_admin' => false,
            'message_markdown' => 'reply '.uniqid('', true),
        ]);
        $reply->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();

        return $reply;
    }

    private function addAdminReply(SupportTicket $ticket, User $admin, Carbon $at): SupportTicketReply
    {
        $reply = SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'is_admin' => true,
            'message_markdown' => 'admin reply '.uniqid('', true),
        ]);
        $reply->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();

        return $reply;
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->saveQuietly();

        return $admin->fresh();
    }

    public function test_handle_closes_tickets_with_old_last_reply_at(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 10, 0, 0));
        Config::set('carpoolear.support_ticket_autoclose_days', 10);

        $user = User::factory()->create();
        $toClose = $this->createResolvedTicket($user, [
            'last_reply_at' => Carbon::now()->subDays(12),
        ]);
        $staysOpen = $this->createResolvedTicket($user, [
            'last_reply_at' => Carbon::now()->subDays(2),
        ]);

        Carbon::setTestNow(Carbon::create(2026, 4, 28, 15, 0, 0));

        $this->artisan('support-tickets:autoclose')
            ->expectsOutput('Support tickets auto-closed: 1')
            ->assertExitCode(0);

        $this->assertSame('Cerrado', $toClose->fresh()->status);
        $this->assertNotNull($toClose->fresh()->closed_at);
        $this->assertSame('2026-04-28 15:00:00', $toClose->fresh()->updated_at->format('Y-m-d H:i:s'));
        $this->assertSame('Resuelto', $staysOpen->fresh()->status);
        $this->assertNull($staysOpen->fresh()->closed_at);
    }

    public function test_handle_closes_when_last_reply_null_and_updated_at_old(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 10, 0, 0));
        Config::set('carpoolear.support_ticket_autoclose_days', 10);

        $user = User::factory()->create();
        $ticket = $this->createResolvedTicket($user, [
            'last_reply_at' => null,
        ]);
        $ticket->forceFill([
            'updated_at' => Carbon::now()->subDays(11),
            'created_at' => Carbon::now()->subDays(20),
        ])->saveQuietly();

        $this->artisan('support-tickets:autoclose')
            ->expectsOutput('Support tickets auto-closed: 1')
            ->assertExitCode(0);

        $this->assertSame('Cerrado', $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->closed_at);
    }

    public function test_command_contract_is_exposed(): void
    {
        $command = new SupportTicketsAutoClose;

        $this->assertSame('support-tickets:autoclose', $command->getName());
        $this->assertStringContainsString('Auto close resolved support tickets', $command->getDescription());
    }

    public function test_handle_closes_waiting_ticket_without_user_reply(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 10, 0, 0));
        Config::set('carpoolear.support_ticket_user_inactivity_days', 30);

        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $ticket = $this->createWaitingTicket($user, [
            'created_at' => Carbon::now()->subDays(45),
            'updated_at' => Carbon::now()->subDays(40),
            'last_reply_at' => Carbon::now()->subDays(40),
        ]);
        $this->addAdminReply($ticket, $admin, Carbon::now()->subDays(40));

        $this->artisan('support-tickets:autoclose')
            ->expectsOutput('Tickets closed for user inactivity: 1')
            ->assertExitCode(0);

        $fresh = $ticket->fresh();
        $this->assertSame('Cerrado', $fresh->status);
        $this->assertNotNull($fresh->closed_at);

        $this->assertTrue(
            SupportTicketReply::query()
                ->where('ticket_id', $ticket->id)
                ->where('is_admin', true)
                ->where('created_by', null)
                ->where('message_markdown', SupportTicketAutoCloseMessage::forUser($user->name))
                ->exists()
        );

        $this->assertTrue(
            DatabaseNotification::query()
                ->where('user_id', $user->id)
                ->where('type', SupportTicketAutoClosedNotification::class)
                ->exists()
        );
    }

    public function test_handle_does_not_close_when_user_replied_recently(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 10, 0, 0));
        Config::set('carpoolear.support_ticket_user_inactivity_days', 30);

        $this->createAdmin();
        $user = User::factory()->create();
        $ticket = $this->createWaitingTicket($user, [
            'created_at' => Carbon::now()->subDays(60),
            'updated_at' => Carbon::now()->subDays(2),
            'last_reply_at' => Carbon::now()->subDays(2),
        ]);
        $this->addUserReply($ticket, $user, Carbon::now()->subDays(2));

        $this->artisan('support-tickets:autoclose')
            ->expectsOutput('Tickets closed for user inactivity: 0')
            ->assertExitCode(0);

        $this->assertSame('Esperando respuesta', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->closed_at);
    }

    public function test_handle_does_not_close_when_admin_replied_recently(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 10, 0, 0));
        Config::set('carpoolear.support_ticket_user_inactivity_days', 30);

        $admin = $this->createAdmin();
        $user = User::factory()->create();
        // The owner never replied, but the team followed up 1 day ago:
        // the user still has a window to answer, so the ticket stays open.
        $ticket = $this->createWaitingTicket($user, [
            'created_at' => Carbon::now()->subDays(90),
            'updated_at' => Carbon::now()->subDays(1),
            'last_reply_at' => Carbon::now()->subDays(1),
        ]);
        $this->addAdminReply($ticket, $admin, Carbon::now()->subDays(1));

        $this->artisan('support-tickets:autoclose')
            ->expectsOutput('Tickets closed for user inactivity: 0')
            ->assertExitCode(0);

        $this->assertSame('Esperando respuesta', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->closed_at);
    }

    public function test_running_twice_does_not_duplicate_auto_close_reply(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 10, 0, 0));
        Config::set('carpoolear.support_ticket_user_inactivity_days', 30);

        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $ticket = $this->createWaitingTicket($user, [
            'created_at' => Carbon::now()->subDays(45),
            'updated_at' => Carbon::now()->subDays(40),
            'last_reply_at' => Carbon::now()->subDays(40),
        ]);
        $this->addAdminReply($ticket, $admin, Carbon::now()->subDays(40));

        $this->artisan('support-tickets:autoclose')->assertExitCode(0);
        // Second run: already closed, so no candidate and no duplicate message.
        $this->artisan('support-tickets:autoclose')
            ->expectsOutput('Tickets closed for user inactivity: 0')
            ->assertExitCode(0);

        $count = SupportTicketReply::query()
            ->where('ticket_id', $ticket->id)
            ->where('message_markdown', SupportTicketAutoCloseMessage::forUser($user->name))
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_respects_configurable_user_inactivity_threshold(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 10, 0, 0));
        Config::set('carpoolear.support_ticket_user_inactivity_days', 60);

        $this->createAdmin();
        $user = User::factory()->create();
        $ticket = $this->createWaitingTicket($user, [
            'created_at' => Carbon::now()->subDays(90),
            'updated_at' => Carbon::now()->subDays(40),
            'last_reply_at' => Carbon::now()->subDays(40),
        ]);
        $this->addUserReply($ticket, $user, Carbon::now()->subDays(40));

        // 40 days < 60-day threshold: stays open.
        $this->artisan('support-tickets:autoclose')
            ->expectsOutput('Tickets closed for user inactivity: 0')
            ->assertExitCode(0);

        $this->assertSame('Esperando respuesta', $ticket->fresh()->status);
    }
}
