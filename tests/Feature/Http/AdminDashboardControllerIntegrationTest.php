<?php

namespace Tests\Feature\Http;

use Carbon\Carbon;
use STS\Http\Middleware\UserAdmin;
use STS\Models\ManualIdentityValidation;
use STS\Models\SupportTicket;
use STS\Models\User;
use Tests\TestCase;

class AdminDashboardControllerIntegrationTest extends TestCase
{
    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->saveQuietly();

        return $user->fresh();
    }

    public function test_show_returns_oldest_ten_manual_validations_ready_for_admin_review(): void
    {
        Carbon::setTestNow('2026-06-18 12:00:00');

        $admin = $this->admin();
        $users = User::factory()->count(12)->create();

        foreach ($users as $index => $user) {
            ManualIdentityValidation::create([
                'user_id' => $user->id,
                'paid' => true,
                'paid_at' => Carbon::parse('2026-06-01 10:00:00')->addDays($index),
                'submitted_at' => Carbon::parse('2026-06-02 10:00:00')->addDays($index),
                'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
            ]);
        }

        ManualIdentityValidation::create([
            'user_id' => User::factory()->create()->id,
            'paid' => false,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        ManualIdentityValidation::create([
            'user_id' => User::factory()->create()->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => null,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        ManualIdentityValidation::create([
            'user_id' => User::factory()->create()->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_APPROVED,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->getJson('api/admin/dashboard')->assertOk();
        $rows = $response->json('data.manual_identity_validations');

        $this->assertCount(10, $rows);
        $this->assertSame([
            'id',
            'user_id',
            'user_name',
            'submitted_at',
            'paid_at',
        ], array_keys($rows[0]));

        $submittedAts = collect($rows)->pluck('submitted_at')->all();
        $sorted = $submittedAts;
        sort($sorted);
        $this->assertSame($sorted, $submittedAts);
    }

    public function test_show_returns_oldest_ten_support_tickets_needing_admin_attention(): void
    {
        Carbon::setTestNow('2026-06-18 12:00:00');

        $admin = $this->admin();
        $owner = User::factory()->create();

        $tickets = [];
        for ($index = 0; $index < 12; $index++) {
            $tickets[] = SupportTicket::create([
                'user_id' => $owner->id,
                'type' => 'feedback',
                'subject' => 'Ticket '.$index,
                'status' => 'Open',
                'priority' => 'normal',
                'unread_for_user' => 0,
                'unread_for_admin' => 0,
                'updated_at' => Carbon::parse('2026-06-01 10:00:00')->addDays($index),
                'created_at' => Carbon::parse('2026-06-01 10:00:00')->addDays($index),
            ]);
        }

        SupportTicket::create([
            'user_id' => $owner->id,
            'type' => 'feedback',
            'subject' => 'Resolved ticket',
            'status' => 'Resuelto',
            'priority' => 'normal',
            'unread_for_user' => 0,
            'unread_for_admin' => 1,
        ]);

        SupportTicket::create([
            'user_id' => $owner->id,
            'type' => 'feedback',
            'subject' => 'Waiting on user',
            'status' => 'Esperando respuesta',
            'priority' => 'normal',
            'unread_for_user' => 0,
            'unread_for_admin' => 0,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->getJson('api/admin/dashboard')->assertOk();
        $rows = $response->json('data.support_tickets');

        $this->assertCount(10, $rows);
        $this->assertSame([
            'id',
            'user_id',
            'user_name',
            'subject',
            'status',
            'updated_at',
        ], array_keys($rows[0]));

        $updatedAts = collect($rows)->pluck('updated_at')->all();
        $sorted = $updatedAts;
        sort($sorted);
        $this->assertSame($sorted, $updatedAts);
        $this->assertSame($tickets[0]->id, $rows[0]['id']);
    }
}
