<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use STS\Http\Middleware\UserAdmin;
use STS\Models\MercadoPagoRejectedValidation;
use STS\Models\SupportTicket;
use STS\Models\User;
use Tests\TestCase;

class AdminMercadoPagoRejectedValidationControllerIntegrationTest extends TestCase
{
    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->saveQuietly();

        return $user->fresh();
    }

    public function test_index_returns_data_newest_first_with_expected_shape(): void
    {
        $admin = $this->admin();
        $uOld = User::factory()->create(['name' => 'Older User']);
        $uNew = User::factory()->create(['name' => 'Newer User', 'nro_doc' => '30111222', 'identity_validated' => false]);

        $older = MercadoPagoRejectedValidation::create([
            'user_id' => $uOld->id,
            'reject_reason' => 'name_mismatch',
            'mp_payload' => ['k' => 'old'],
        ]);
        $newer = MercadoPagoRejectedValidation::create([
            'user_id' => $uNew->id,
            'reject_reason' => 'dni_mismatch',
            'mp_payload' => ['k' => 'new'],
        ]);

        DB::table('mercado_pago_rejected_validations')->where('id', $older->id)->update([
            'created_at' => now()->subDays(3),
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->getJson('api/admin/mercado-pago-rejected-validations')->assertOk();
        $this->assertSame(['data'], array_keys($response->json()));

        $rows = collect($response->json('data'));
        $this->assertGreaterThanOrEqual(2, $rows->count());

        $first = $rows->first();
        $this->assertSame($newer->id, (int) ($first['id'] ?? 0));
        $this->assertSame($uNew->id, (int) ($first['user_id'] ?? 0));
        $this->assertSame('Newer User', $first['user_name']);
        $this->assertSame('30111222', $first['user_nro_doc']);
        $this->assertFalse((bool) $first['user_identity_validated']);
        $this->assertSame('dni_mismatch', $first['reject_reason']);
        $this->assertArrayHasKey('review_status', $first);
        $this->assertArrayHasKey('created_at', $first);
    }

    public function test_show_returns_single_row_payload_and_review_approve_updates_user(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create([
            'identity_validated' => false,
            'identity_validated_at' => null,
            'identity_validation_type' => null,
        ]);

        $row = MercadoPagoRejectedValidation::create([
            'user_id' => $user->id,
            'reject_reason' => 'dni_mismatch',
            'mp_payload' => ['sub' => 'mp-1'],
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $show = $this->getJson('api/admin/mercado-pago-rejected-validations/'.$row->id)->assertOk();
        $this->assertSame(['data'], array_keys($show->json()));
        $data = $show->json('data');
        $this->assertSame($row->id, (int) ($data['id'] ?? 0));
        $this->assertSame($user->email, $data['user_email']);
        $this->assertSame(['sub' => 'mp-1'], $data['mp_payload']);
        $this->assertNull($data['approved_at']);
        $this->assertNull($data['approved_by']);
        $this->assertNull($data['approved_by_name']);
        $this->assertNull($data['reviewed_at']);
        $this->assertNull($data['reviewed_by']);
        $this->assertNull($data['reviewed_by_name']);
        $this->assertNull($data['private_admin_note']);
        $this->assertFalse($data['user_identity_validated']);

        $approved = $this->postJson('api/admin/mercado-pago-rejected-validations/'.$row->id.'/review', [
            'action' => 'approve',
        ])->assertOk();
        $approved->assertJsonPath('data.review_status', 'approved');
        $approved->assertJsonPath('data.review_note', '');

        $user->refresh();
        $this->assertTrue((bool) $user->identity_validated);
        $this->assertSame('manual', (string) $user->identity_validation_type);
        $this->assertNotNull($user->identity_validated_at);
    }

    public function test_index_maps_identity_validated_true_when_user_is_validated(): void
    {
        $admin = $this->admin();
        $validatedUser = User::factory()->create([
            'name' => 'Verified Person',
            'identity_validated' => true,
        ]);
        $row = MercadoPagoRejectedValidation::create([
            'user_id' => $validatedUser->id,
            'reject_reason' => 'name_mismatch',
            'mp_payload' => ['x' => 1],
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $hit = collect($this->getJson('api/admin/mercado-pago-rejected-validations')->assertOk()->json('data'))
            ->firstWhere('id', $row->id);

        $this->assertNotNull($hit);
        $this->assertTrue($hit['user_identity_validated']);
        $this->assertSame('Verified Person', $hit['user_name']);
    }

    public function test_index_maps_null_user_fields_when_user_id_has_no_matching_row(): void
    {
        $admin = $this->admin();
        $missingUserId = (int) (User::query()->max('id') ?? 0) + 50_000;

        Schema::disableForeignKeyConstraints();
        try {
            $orphanId = (int) DB::table('mercado_pago_rejected_validations')->insertGetId([
                'user_id' => $missingUserId,
                'reject_reason' => 'dni_mismatch',
                'mp_payload' => json_encode(['orphan' => true]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $hit = collect($this->getJson('api/admin/mercado-pago-rejected-validations')->assertOk()->json('data'))
            ->firstWhere('id', $orphanId);

        $this->assertNotNull($hit);
        $this->assertNull($hit['user_name']);
        $this->assertNull($hit['user_nro_doc']);
        $this->assertFalse($hit['user_identity_validated']);

        Schema::disableForeignKeyConstraints();
        try {
            DB::table('mercado_pago_rejected_validations')->where('id', $orphanId)->delete();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function test_show_returns_full_audit_fields_after_approval(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['identity_validated' => false]);
        $row = MercadoPagoRejectedValidation::create([
            'user_id' => $user->id,
            'reject_reason' => 'dni_mismatch',
            'mp_payload' => ['first_name' => 'A'],
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/mercado-pago-rejected-validations/'.$row->id.'/review', [
            'action' => 'approve',
        ])->assertOk();

        $data = $this->getJson('api/admin/mercado-pago-rejected-validations/'.$row->id)->assertOk()->json('data');

        $this->assertSame($admin->name, $data['approved_by_name']);
        $this->assertSame($admin->name, $data['reviewed_by_name']);
        $this->assertSame($admin->id, (int) $data['approved_by']);
        $this->assertSame($admin->id, (int) $data['reviewed_by']);
        $this->assertNotNull($data['approved_at']);
        $this->assertNotNull($data['reviewed_at']);
        $this->assertSame('approved', $data['review_status']);
        $this->assertSame(['first_name' => 'A'], $data['mp_payload']);
    }

    public function test_review_reject_clears_identity_and_returns_rejected_payload(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create([
            'identity_validated' => true,
            'identity_validation_type' => 'mercado_pago',
        ]);
        $row = MercadoPagoRejectedValidation::create([
            'user_id' => $user->id,
            'reject_reason' => 'name_mismatch',
            'mp_payload' => ['k' => 2],
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $payload = $this->postJson('api/admin/mercado-pago-rejected-validations/'.$row->id.'/review', [
            'action' => 'reject',
            'note' => 'Still not matching documents.',
        ])->assertOk()->json('data');

        $this->assertSame('rejected', $payload['review_status']);
        $this->assertSame('Still not matching documents.', $payload['review_note']);
        $this->assertFalse($payload['user_identity_validated']);
        $this->assertNull($payload['approved_at']);
        $this->assertNull($payload['approved_by']);
        $this->assertNull($payload['approved_by_name']);

        $user->refresh();
        $this->assertFalse((bool) $user->identity_validated);
        $this->assertNull($user->identity_validation_type);
    }

    public function test_review_pending_sets_status_and_requires_note(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['identity_validated' => false]);
        $row = MercadoPagoRejectedValidation::create([
            'user_id' => $user->id,
            'reject_reason' => 'dni_mismatch',
            'mp_payload' => [],
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/mercado-pago-rejected-validations/'.$row->id.'/review', [
            'action' => 'pending',
        ])->assertUnprocessable();

        $this->postJson('api/admin/mercado-pago-rejected-validations/'.$row->id.'/review', [
            'action' => 'pending',
            'note' => 'Needs manual follow-up.',
        ])->assertOk()->assertJsonPath('data.review_status', 'pending')
            ->assertJsonPath('data.review_note', 'Needs manual follow-up.');
    }

    public function test_review_returns_not_found_when_user_relation_is_missing(): void
    {
        $admin = $this->admin();
        $missingUserId = (int) (User::query()->max('id') ?? 0) + 60_000;

        Schema::disableForeignKeyConstraints();
        try {
            $orphanId = (int) DB::table('mercado_pago_rejected_validations')->insertGetId([
                'user_id' => $missingUserId,
                'reject_reason' => 'name_mismatch',
                'mp_payload' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/mercado-pago-rejected-validations/'.$orphanId.'/review', [
            'action' => 'approve',
        ])->assertNotFound()->assertJsonPath('error', 'User not found.');

        Schema::disableForeignKeyConstraints();
        try {
            DB::table('mercado_pago_rejected_validations')->where('id', $orphanId)->delete();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function test_post_legacy_approve_matches_review_approve(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['identity_validated' => false]);
        $row = MercadoPagoRejectedValidation::create([
            'user_id' => $user->id,
            'reject_reason' => 'dni_mismatch',
            'mp_payload' => [],
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/mercado-pago-rejected-validations/'.$row->id.'/approve', [])
            ->assertOk()
            ->assertJsonPath('data.review_status', 'approved');

        $this->assertTrue($user->fresh()->identity_validated);
    }

    public function test_private_note_can_be_updated_by_admin_and_returned_in_show(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['identity_validated' => false]);
        $row = MercadoPagoRejectedValidation::create([
            'user_id' => $user->id,
            'reject_reason' => 'dni_mismatch',
            'mp_payload' => [],
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/mercado-pago-rejected-validations/'.$row->id.'/private-note', [
            'private_admin_note' => 'Contacto por telefono, revisar en 48hs.',
        ])->assertOk()->assertJsonPath('data.private_admin_note', 'Contacto por telefono, revisar en 48hs.');

        $show = $this->getJson('api/admin/mercado-pago-rejected-validations/'.$row->id)->assertOk();
        $this->assertSame('Contacto por telefono, revisar en 48hs.', $show->json('data.private_admin_note'));
    }

    public function test_show_includes_support_tickets_count_for_user(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $row = MercadoPagoRejectedValidation::create([
            'user_id' => $user->id,
            'reject_reason' => 'dni_mismatch',
            'mp_payload' => ['sub' => 'mp-1'],
        ]);

        SupportTicket::create([
            'user_id' => $user->id,
            'type' => 'account_verification',
            'subject' => 'Verification help',
            'status' => 'Open',
            'priority' => 'high',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $data = $this->getJson('api/admin/mercado-pago-rejected-validations/'.$row->id)->assertOk()->json('data');

        $this->assertArrayHasKey('support_tickets_count', $data);
        $this->assertSame(1, $data['support_tickets_count']);
    }
}
