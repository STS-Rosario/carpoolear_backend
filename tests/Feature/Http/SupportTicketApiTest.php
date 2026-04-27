<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use STS\Models\User;
use Tests\TestCase;

class SupportTicketApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_creates_ticket_with_attachments_and_admin_reply_updates_unread_counters(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user, 'api');
        $createResponse = $this->post('api/support/tickets', [
            'type' => 'bug_report',
            'subject' => 'App crash',
            'message_markdown' => 'Steps to reproduce',
            'priority' => 'normal',
            'attachments' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
            ],
        ]);

        $createResponse->assertStatus(200);
        $ticketId = (int) data_get($createResponse->json(), 'data.id');
        $this->assertGreaterThan(0, $ticketId);
        $this->assertSame('Open', data_get($createResponse->json(), 'data.status'));
        $this->assertSame(0, data_get($createResponse->json(), 'data.unread_for_user'));
        $this->assertSame(1, data_get($createResponse->json(), 'data.unread_for_admin'));

        $this->actingAs($admin, 'api');
        $adminReply = $this->post("api/admin/support/tickets/{$ticketId}/replies", [
            'message_markdown' => 'We are reviewing it.',
        ]);
        $adminReply->assertStatus(200);
        $adminReply->assertJsonPath('data.status', 'En revision');
        $adminReply->assertJsonPath('data.unread_for_user', 1);
        $adminReply->assertJsonPath('data.unread_for_admin', 0);
    }

    public function test_status_actions_and_visibility_constraints(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($owner, 'api');
        $created = $this->post('api/support/tickets', [
            'type' => 'feedback',
            'subject' => 'Nice feature',
            'message_markdown' => 'Thank you team',
        ]);
        $created->assertStatus(200);
        $ticketId = (int) data_get($created->json(), 'data.id');

        $this->actingAs($otherUser, 'api');
        $this->get("api/support/tickets/{$ticketId}")->assertStatus(404);

        $this->actingAs($admin, 'api');
        $this->post("api/admin/support/tickets/{$ticketId}/resolve", [
            'message_markdown' => 'Resolved in latest release.',
        ])->assertStatus(200)->assertJsonPath('data.status', 'Resuelto');

        $this->post("api/admin/support/tickets/{$ticketId}/close", [
            'message_markdown' => 'Closing ticket.',
        ])->assertStatus(200)->assertJsonPath('data.status', 'Cerrado');

        $this->post("api/admin/support/tickets/{$ticketId}/reopen")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'En revision');
    }

    public function test_ticket_rate_limit_is_applied(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        config()->set('carpoolear.support_ticket_rate_limit_create_per_hour', 1);

        $this->post('api/support/tickets', [
            'type' => 'contact',
            'subject' => 'Need help',
            'message_markdown' => 'First',
        ])->assertStatus(200);

        $this->post('api/support/tickets', [
            'type' => 'contact',
            'subject' => 'Need more help',
            'message_markdown' => 'Second',
        ])->assertStatus(429);
    }
}
