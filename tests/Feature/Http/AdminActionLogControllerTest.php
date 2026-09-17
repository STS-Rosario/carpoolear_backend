<?php

namespace Tests\Feature\Http;

use STS\Http\Middleware\UserAdmin;
use STS\Models\AdminActionLog;
use STS\Models\DeleteAccountRequest;
use STS\Models\ManualIdentityValidation;
use STS\Models\SupportTicket;
use STS\Models\User;
use Tests\TestCase;

class AdminActionLogControllerTest extends TestCase
{
    private function staff(string $role): User
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $user->forceFill(['is_admin' => true, 'admin_role' => $role])->saveQuietly();

        return $user->fresh();
    }

    private function actingAsStaff(User $user): void
    {
        $this->actingAs($user, 'api');
        $this->withoutMiddleware(UserAdmin::class);
    }

    public function test_helpdesk_can_list_action_logs_filtered_by_admin_action_dates_and_target(): void
    {
        $helpdesk = $this->staff('helpdesk');
        $otherAdmin = $this->staff('superadmin');
        $target = User::factory()->create();
        $otherTarget = User::factory()->create();

        $matching = AdminActionLog::query()->create([
            'admin_user_id' => $helpdesk->id,
            'action' => AdminActionLog::ACTION_USER_UPDATE,
            'target_user_id' => $target->id,
            'details' => ['keys' => ['description']],
        ]);
        $matching->forceFill([
            'created_at' => '2026-09-10 12:00:00',
            'updated_at' => '2026-09-10 12:00:00',
        ])->saveQuietly();
        $other = AdminActionLog::query()->create([
            'admin_user_id' => $otherAdmin->id,
            'action' => AdminActionLog::ACTION_USER_DELETE,
            'target_user_id' => $otherTarget->id,
            'details' => [],
        ]);
        $other->forceFill([
            'created_at' => '2026-09-01 12:00:00',
            'updated_at' => '2026-09-01 12:00:00',
        ])->saveQuietly();

        $this->actingAsStaff($helpdesk);

        $response = $this->getJson(
            'api/admin/action-logs?admin_user_id='.$helpdesk->id
            .'&action='.AdminActionLog::ACTION_USER_UPDATE
            .'&target_user_id='.$target->id
            .'&from=2026-09-09'
            .'&to=2026-09-11'
        );

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertCount(1, $ids);
        $row = $response->json('data.0');
        $this->assertSame(AdminActionLog::ACTION_USER_UPDATE, $row['action']);
        $this->assertSame($helpdesk->id, $row['admin_user_id']);
        $this->assertSame($helpdesk->name, $row['admin_user_name']);
        $this->assertSame($target->id, $row['target_user_id']);
        $this->assertSame($target->name, $row['target_user_name']);
        $this->assertSame(['keys' => ['description']], $row['details']);
        $this->assertArrayHasKey('pagination', $response->json('meta'));
    }

    public function test_admin_update_logs_changed_keys_for_the_actor(): void
    {
        $staff = $this->staff('helpdesk');
        $target = User::factory()->create([
            'active' => true,
            'banned' => false,
            'description' => 'before',
        ]);
        $this->actingAsStaff($staff);

        $this->putJson('api/users/modify', [
            'user' => ['id' => $target->id],
            'description' => 'after log',
        ])->assertOk();

        $this->assertDatabaseHas('admin_action_logs', [
            'admin_user_id' => $staff->id,
            'action' => AdminActionLog::ACTION_USER_UPDATE,
            'target_user_id' => $target->id,
        ]);
        $details = AdminActionLog::query()
            ->where('admin_user_id', $staff->id)
            ->where('action', AdminActionLog::ACTION_USER_UPDATE)
            ->latest('id')
            ->value('details');
        $decoded = is_string($details) ? json_decode($details, true) : $details;
        $this->assertContains('description', $decoded['keys'] ?? []);
    }

    public function test_identity_review_logs_the_review_action(): void
    {
        $staff = $this->staff('helpdesk');
        $target = User::factory()->create(['identity_validated' => false]);
        $row = ManualIdentityValidation::query()->create([
            'user_id' => $target->id,
            'paid' => true,
            'paid_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);
        $this->actingAsStaff($staff);

        $this->postJson('api/admin/manual-identity-validations/'.$row->id.'/review', [
            'action' => 'approve',
        ])->assertOk();

        $this->assertDatabaseHas('admin_action_logs', [
            'admin_user_id' => $staff->id,
            'action' => AdminActionLog::ACTION_IDENTITY_REVIEW,
            'target_user_id' => $target->id,
        ]);
    }

    public function test_account_delete_update_logs_the_status_change(): void
    {
        $staff = $this->staff('helpdesk');
        $owner = User::factory()->create();
        $row = DeleteAccountRequest::query()->create([
            'user_id' => $owner->id,
            'date_requested' => now()->subDay(),
            'action_taken' => DeleteAccountRequest::ACTION_REQUESTED,
            'action_taken_date' => null,
        ]);
        $this->actingAsStaff($staff);

        $this->postJson('api/admin/users/account-delete-update', [
            'id' => $row->id,
            'action_taken' => DeleteAccountRequest::ACTION_REJECTED,
        ])->assertOk();

        $this->assertDatabaseHas('admin_action_logs', [
            'admin_user_id' => $staff->id,
            'action' => AdminActionLog::ACTION_ACCOUNT_DELETE_REQUEST_UPDATE,
            'target_user_id' => $owner->id,
        ]);
    }

    public function test_support_ticket_status_update_logs_the_mutation(): void
    {
        $staff = $this->staff('helpdesk');
        $owner = User::factory()->create();
        $ticket = SupportTicket::query()->create([
            'user_id' => $owner->id,
            'type' => 'feedback',
            'subject' => 'Audit log ticket',
            'status' => 'Open',
            'priority' => 'normal',
            'unread_for_user' => 0,
            'unread_for_admin' => 0,
        ]);
        $this->actingAsStaff($staff);

        $this->patchJson('api/admin/support/tickets/'.$ticket->id.'/status', [
            'status' => 'Cerrado',
        ])->assertOk();

        $this->assertDatabaseHas('admin_action_logs', [
            'admin_user_id' => $staff->id,
            'action' => AdminActionLog::ACTION_SUPPORT_TICKET_UPDATE,
            'target_user_id' => $owner->id,
        ]);
    }

    public function test_maintenance_state_update_logs_with_nullable_target(): void
    {
        $staff = $this->staff('superadmin');
        $this->actingAsStaff($staff);

        $this->putJson('api/admin/maintenance/state', [
            'active' => false,
        ])->assertOk();

        $this->assertDatabaseHas('admin_action_logs', [
            'admin_user_id' => $staff->id,
            'action' => AdminActionLog::ACTION_MAINTENANCE_UPDATE,
            'target_user_id' => null,
        ]);
    }
}
