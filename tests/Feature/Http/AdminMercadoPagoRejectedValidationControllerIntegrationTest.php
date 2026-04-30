<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\DB;
use STS\Http\Middleware\UserAdmin;
use STS\Models\MercadoPagoRejectedValidation;
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

        $this->postJson('api/admin/mercado-pago-rejected-validations/'.$row->id.'/review', [
            'action' => 'approve',
        ])->assertOk()->assertJsonPath('data.review_status', 'approved');

        $user->refresh();
        $this->assertTrue((bool) $user->identity_validated);
        $this->assertSame('manual', (string) $user->identity_validation_type);
        $this->assertNotNull($user->identity_validated_at);
    }
}
