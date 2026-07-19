<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use STS\Console\Commands\SupportTicketsReleaseExpiredAssignments;
use STS\Models\SupportTicket;
use STS\Models\User;
use Tests\TestCase;

class SupportTicketsReleaseExpiredAssignmentsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_handle_releases_expired_assignments(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 19, 12, 0, 0));
        Config::set('carpoolear.support_ticket_assignment_timeout_minutes', 10);

        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $active = SupportTicket::query()->create([
            'user_id' => $owner->id,
            'type' => 'contact',
            'subject' => 'Active',
            'status' => 'Open',
            'priority' => 'normal',
            'assigned_to_user_id' => $admin->id,
            'assigned_at' => Carbon::now()->subMinutes(5),
        ]);
        $expired = SupportTicket::query()->create([
            'user_id' => $owner->id,
            'type' => 'contact',
            'subject' => 'Expired',
            'status' => 'Open',
            'priority' => 'normal',
            'assigned_to_user_id' => $admin->id,
            'assigned_at' => Carbon::now()->subMinutes(11),
        ]);

        $this->artisan('support-tickets:release-expired-assignments')
            ->expectsOutput('Support ticket assignments released: 1')
            ->assertExitCode(0);

        $this->assertSame($admin->id, (int) $active->fresh()->assigned_to_user_id);
        $this->assertNull($expired->fresh()->assigned_to_user_id);
        $this->assertNull($expired->fresh()->assigned_at);
    }

    public function test_command_contract_is_exposed(): void
    {
        $command = new SupportTicketsReleaseExpiredAssignments(
            $this->app->make(\STS\Services\SupportTicketService::class)
        );

        $this->assertSame('support-tickets:release-expired-assignments', $command->getName());
        $this->assertStringContainsString('Release expired support ticket assignments', $command->getDescription());
    }
}
