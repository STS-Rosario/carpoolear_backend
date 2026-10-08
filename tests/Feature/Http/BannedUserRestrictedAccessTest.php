<?php

namespace Tests\Feature\Http;

use STS\Models\SupportTicket;
use STS\Models\User;
use Tests\TestCase;

class BannedUserRestrictedAccessTest extends TestCase
{
    public function test_banned_user_can_load_own_profile_with_banned_flag(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'banned' => true,
        ]);

        $this->actingAs($user, 'api');

        $this->getJson('api/users/me')
            ->assertOk()
            ->assertJsonPath('data.banned', 1);
    }

    public function test_banned_user_can_list_existing_tickets_but_cannot_create_new_ones(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'banned' => true,
        ]);

        SupportTicket::create([
            'user_id' => $user->id,
            'type' => 'contact',
            'source' => SupportTicket::SOURCE_WEB_FORM,
            'subject' => 'Existing ticket',
            'status' => 'Open',
            'priority' => 'normal',
            'unread_for_user' => 0,
            'unread_for_admin' => 1,
            'created_by' => $user->id,
            'last_reply_at' => now(),
        ]);

        $this->actingAs($user, 'api');

        $this->getJson('api/support/tickets')
            ->assertOk()
            ->assertJsonPath('data.0.subject', 'Existing ticket');

        $this->postJson('api/support/tickets', [
            'type' => 'contact',
            'subject' => 'Please unban me',
            'message_markdown' => 'I want to create a new ticket',
        ])->assertForbidden();
    }

    public function test_banned_user_cannot_search_trips(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'banned' => true,
        ]);

        $this->actingAs($user, 'api');

        $this->getJson('api/trips')->assertForbidden();
    }
}
