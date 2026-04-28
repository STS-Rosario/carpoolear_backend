<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use STS\Console\Commands\SupportTicketsAutoClose;
use STS\Models\SupportTicket;
use STS\Models\User;
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

        $this->artisan('support-tickets:autoclose')
            ->expectsOutput('Support tickets auto-closed: 1')
            ->assertExitCode(0);

        $this->assertSame('Cerrado', $toClose->fresh()->status);
        $this->assertNotNull($toClose->fresh()->closed_at);
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
}
