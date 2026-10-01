<?php

namespace Tests\Feature\Http;

use STS\Http\Middleware\UserAdmin;
use STS\Models\IdentityVerificationEvent;
use STS\Models\ManualIdentityValidation;
use STS\Models\MercadoPagoRejectedValidation;
use STS\Models\User;
use Tests\TestCase;

class AdminIdentityVerificationEventsTest extends TestCase
{
    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->saveQuietly();
        $admin = $admin->fresh();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        return $admin;
    }

    private function paidPendingRequest(User $user): ManualIdentityValidation
    {
        return ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now(),
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);
    }

    public function test_manual_state_override_records_admin_state_changed_with_new_status(): void
    {
        $admin = $this->actingAsAdmin();
        $user = User::factory()->create(['identity_validated' => false]);
        $row = $this->paidPendingRequest($user);

        $this->postJson('api/admin/manual-identity-validations/'.$row->id.'/state', [
            'review_status' => 'approved',
        ])->assertOk();

        $event = IdentityVerificationEvent::query()
            ->where('name', 'admin_state_changed')
            ->sole();

        $this->assertSame($user->id, $event->user_id);
        $this->assertSame('manual', $event->method);
        $this->assertSame('approved', $event->reason);
        $this->assertSame('manual_identity_validations', $event->related_type);
        $this->assertSame($row->id, $event->related_id);
        $this->assertSame('pending', $event->metadata['previous_status'] ?? null);
        $this->assertTrue($event->metadata['paid'] ?? null);
        $this->assertSame($admin->id, $event->metadata['admin_id'] ?? null);
    }

    public function test_manual_state_override_marking_paid_records_resulting_status(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create();
        $row = ManualIdentityValidation::create([
            'user_id' => $user->id,
            'paid' => false,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->postJson('api/admin/manual-identity-validations/'.$row->id.'/state', [
            'paid' => true,
        ])->assertOk();

        $event = IdentityVerificationEvent::query()
            ->where('name', 'admin_state_changed')
            ->sole();

        $this->assertSame('awaiting_photos', $event->reason);
        $this->assertSame('pending', $event->metadata['previous_status'] ?? null);
        $this->assertFalse($event->metadata['previous_paid'] ?? null);
        $this->assertTrue($event->metadata['paid'] ?? null);
    }

    public function test_manual_state_override_without_effective_change_records_nothing(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create();
        $row = $this->paidPendingRequest($user);

        $this->postJson('api/admin/manual-identity-validations/'.$row->id.'/state', [
            'review_status' => 'pending',
            'paid' => true,
        ])->assertOk();

        $this->assertSame(0, IdentityVerificationEvent::query()->where('name', 'admin_state_changed')->count());
    }

    private function mpRejection(User $user): MercadoPagoRejectedValidation
    {
        return MercadoPagoRejectedValidation::create([
            'user_id' => $user->id,
            'reject_reason' => 'dni_mismatch',
            'mp_payload' => ['first_name' => 'Jane'],
        ]);
    }

    public function test_mp_rejection_review_reject_records_failed_with_reason(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create(['identity_validated' => false]);
        $row = $this->mpRejection($user);

        $this->postJson('api/admin/mercado-pago-rejected-validations/'.$row->id.'/review', [
            'action' => 'reject',
            'note' => 'DNI does not match.',
        ])->assertOk();

        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'method' => 'manual',
            'name' => 'failed',
            'reason' => 'rejected_from_mp_rejection',
            'related_type' => 'mercado_pago_rejected_validations',
            'related_id' => $row->id,
        ]);
    }

    public function test_mp_rejection_review_pending_records_info_requested_with_reason(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create(['identity_validated' => false]);
        $row = $this->mpRejection($user);

        $this->postJson('api/admin/mercado-pago-rejected-validations/'.$row->id.'/review', [
            'action' => 'pending',
            'note' => 'Please contact support.',
        ])->assertOk();

        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'method' => 'manual',
            'name' => 'info_requested',
            'reason' => 'pending_from_mp_rejection',
            'related_type' => 'mercado_pago_rejected_validations',
            'related_id' => $row->id,
        ]);
    }

    private function superadmin(): User
    {
        $admin = User::factory()->create(['active' => true, 'banned' => false]);
        $admin->forceFill(['is_admin' => true, 'admin_role' => 'superadmin'])->saveQuietly();
        $admin = $admin->fresh();
        $this->actingAs($admin, 'api');

        return $admin;
    }

    public function test_admin_profile_edit_validating_identity_records_admin_identity_edited(): void
    {
        $admin = $this->superadmin();
        $target = User::factory()->create(['active' => true, 'banned' => false, 'identity_validated' => false]);

        $this->putJson('api/users/modify', [
            'user' => ['id' => $target->id],
            'identity_validated' => true,
        ])->assertOk();

        $this->assertTrue((bool) $target->fresh()->identity_validated);
        $event = IdentityVerificationEvent::query()->where('name', 'admin_identity_edited')->sole();
        $this->assertSame($target->id, $event->user_id);
        $this->assertSame('admin', $event->method);
        $this->assertSame('validated', $event->reason);
        $this->assertSame('users', $event->related_type);
        $this->assertSame($target->id, $event->related_id);
        $this->assertSame($admin->id, $event->metadata['admin_id'] ?? null);
    }

    public function test_admin_profile_edit_unvalidating_identity_records_unvalidated(): void
    {
        $this->superadmin();
        $target = User::factory()->create([
            'active' => true,
            'banned' => false,
            'identity_validated' => true,
            'identity_validated_at' => now(),
        ]);

        $this->putJson('api/users/modify', [
            'user' => ['id' => $target->id],
            'identity_validated' => false,
        ])->assertOk();

        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $target->id,
            'method' => 'admin',
            'name' => 'admin_identity_edited',
            'reason' => 'unvalidated',
        ]);
    }

    public function test_admin_profile_edit_without_identity_change_records_nothing(): void
    {
        $this->superadmin();
        $target = User::factory()->create(['active' => true, 'banned' => false, 'identity_validated' => false]);

        $this->putJson('api/users/modify', [
            'user' => ['id' => $target->id],
            'description' => 'changed',
            'identity_validated' => false,
        ])->assertOk();

        $this->assertSame(0, IdentityVerificationEvent::query()->count());
    }
}
