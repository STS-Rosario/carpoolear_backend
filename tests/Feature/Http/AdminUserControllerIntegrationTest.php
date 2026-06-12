<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Storage;
use STS\Http\Middleware\UserAdmin;
use STS\Models\AdminActionLog;
use STS\Models\BannedUser;
use STS\Models\DeleteAccountRequest;
use STS\Models\ManualIdentityValidation;
use STS\Models\MercadoPagoRejectedValidation;
use STS\Models\Rating;
use STS\Models\Trip;
use STS\Models\User;
use STS\Services\Logic\DeviceManager;
use Tests\TestCase;

class AdminUserControllerIntegrationTest extends TestCase
{
    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->saveQuietly();

        return $user->fresh();
    }

    public function test_index_whitespace_only_name_param_does_not_apply_like_filter(): void
    {
        $admin = $this->admin();
        $needle = 'admuniq-'.uniqid('', true);
        $u1 = User::factory()->create(['name' => 'Alpha '.$needle, 'email' => 'a'.$needle.'@example.com']);
        $u2 = User::factory()->create(['name' => 'Beta '.$needle, 'email' => 'b'.$needle.'@example.com']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->getJson('api/admin/users?name='.urlencode('   ').'&per_page=100');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($u1->id, $ids);
        $this->assertContains($u2->id, $ids);
    }

    public function test_index_name_search_trims_and_matches_email_field(): void
    {
        $admin = $this->admin();
        $token = 'mailtoken-'.uniqid('', true);
        $hit = User::factory()->create([
            'name' => 'Other Name',
            'email' => $token.'@example.com',
        ]);
        User::factory()->create([
            'name' => 'Unrelated',
            'email' => 'zzz-'.$token.'@example.com',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->getJson('api/admin/users?name='.urlencode('  '.$token.'  '));
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($hit->id, $ids);
        $this->assertLessThanOrEqual(5, count($ids));
    }

    public function test_index_invalid_sort_defaults_to_id_ordering(): void
    {
        $admin = $this->admin();
        $first = User::factory()->create(['name' => 'SortZzz']);
        $second = User::factory()->create(['name' => 'SortAaa']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $ids = collect($this->getJson('api/admin/users?sort=not_a_column&direction=desc&per_page=50')->assertOk()->json('data'))->pluck('id')->all();
        $posFirst = array_search($first->id, $ids, true);
        $posSecond = array_search($second->id, $ids, true);
        $this->assertNotFalse($posFirst);
        $this->assertNotFalse($posSecond);
        $this->assertLessThan($posFirst, $posSecond, 'Default sort id desc: higher id should appear first');
    }

    public function test_index_direction_accepts_asc_for_name_sort(): void
    {
        $admin = $this->admin();
        $token = 'sortname-'.uniqid('', true);
        $alice = User::factory()->create(['name' => 'Alice '.$token, 'email' => 'alice-'.$token.'@example.com']);
        $bob = User::factory()->create(['name' => 'Bob '.$token, 'email' => 'bob-'.$token.'@example.com']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $namesAsc = collect($this->getJson('api/admin/users?name='.urlencode($token).'&sort=name&direction=asc&per_page=50')->json('data'))
            ->pluck('name')
            ->values()
            ->all();

        $this->assertContains($alice->name, $namesAsc);
        $this->assertContains($bob->name, $namesAsc);
        $aliceIdx = array_search($alice->name, $namesAsc, true);
        $bobIdx = array_search($bob->name, $namesAsc, true);
        $this->assertNotFalse($aliceIdx);
        $this->assertNotFalse($bobIdx);
        $this->assertLessThan($bobIdx, $aliceIdx);
    }

    public function test_index_per_page_is_clamped_to_max_hundred(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->getJson('api/admin/users?per_page=500')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 100);
    }

    public function test_delete_removes_user_without_trips_and_logs_admin_action(): void
    {
        $this->spy(DeviceManager::class);

        $admin = $this->admin();
        $target = User::factory()->create();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/users/'.$target->id.'/delete')
            ->assertOk()
            ->assertJsonPath('action', 'deleted');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseHas('admin_action_logs', [
            'admin_user_id' => $admin->id,
            'action' => AdminActionLog::ACTION_USER_DELETE,
            'target_user_id' => $target->id,
        ]);

        app(DeviceManager::class)->shouldHaveReceived('logoutAllDevices')->once();
    }

    public function test_delete_returns_unprocessable_when_user_has_trips(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        Trip::factory()->create(['user_id' => $target->id]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/users/'.$target->id.'/delete')
            ->assertUnprocessable();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_clear_identity_validation_clears_identity_columns(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create([
            'identity_validated' => true,
            'identity_validated_at' => now(),
            'identity_validation_type' => 'mercado_pago',
            'identity_validation_rejected_at' => now()->subDay(),
            'identity_validation_reject_reason' => 'name_mismatch',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/users/'.$target->id.'/clear-identity-validation')
            ->assertOk()
            ->assertJsonPath('message', 'Identity validation cleared')
            ->assertJsonPath('data.identity_validated', false)
            ->assertJsonPath('data.identity_validation_type', null);

        $fresh = $target->fresh();
        $this->assertFalse($fresh->identity_validated);
        $this->assertNull($fresh->identity_validated_at);
        $this->assertNull($fresh->identity_validation_type);
        $this->assertNull($fresh->identity_validation_rejected_at);
        $this->assertNull($fresh->identity_validation_reject_reason);
    }

    public function test_clear_identity_validation_deletes_manual_rows_photos_and_mp_rejections(): void
    {
        Storage::fake('local');

        $admin = $this->admin();
        $target = User::factory()->create([
            'identity_validated' => false,
            'identity_validated_at' => null,
            'identity_validation_type' => null,
            'identity_validation_rejected_at' => now()->subDay(),
            'identity_validation_reject_reason' => 'name_mismatch',
        ]);

        $frontPath = 'idv/clear-front.jpg';
        Storage::disk('local')->put($frontPath, 'front-bytes');

        $manual = ManualIdentityValidation::query()->create([
            'user_id' => $target->id,
            'paid' => true,
            'paid_at' => now(),
            'submitted_at' => now(),
            'front_image_path' => $frontPath,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_REJECTED,
            'review_note' => 'Prueba',
        ]);

        $mpRejected = MercadoPagoRejectedValidation::query()->create([
            'user_id' => $target->id,
            'reject_reason' => 'dni_mismatch',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/users/'.$target->id.'/clear-identity-validation')
            ->assertOk()
            ->assertJsonPath('message', 'Identity validation cleared');

        $this->assertDatabaseMissing('manual_identity_validations', ['id' => $manual->id]);
        $this->assertDatabaseMissing('mercado_pago_rejected_validations', ['id' => $mpRejected->id]);
        Storage::disk('local')->assertMissing($frontPath);

        $fresh = $target->fresh();
        $this->assertFalse($fresh->identity_validated);
        $this->assertNull($fresh->identity_validated_at);
        $this->assertNull($fresh->identity_validation_type);
        $this->assertNull($fresh->identity_validation_rejected_at);
        $this->assertNull($fresh->identity_validation_reject_reason);
    }

    public function test_account_delete_update_persists_action_fields(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        $row = DeleteAccountRequest::query()->create([
            'user_id' => $owner->id,
            'date_requested' => now()->subDay(),
            'action_taken' => DeleteAccountRequest::ACTION_REQUESTED,
            'action_taken_date' => null,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/users/account-delete-update', [
            'id' => $row->id,
            'action_taken' => DeleteAccountRequest::ACTION_DELETED,
        ])
            ->assertOk()
            ->assertJsonPath('data.action_taken', DeleteAccountRequest::ACTION_DELETED);

        $row->refresh();
        $this->assertSame(DeleteAccountRequest::ACTION_DELETED, (int) $row->action_taken);
        $this->assertNotNull($row->action_taken_date);
    }

    public function test_ban_and_anonymize_appends_identity_when_user_has_no_nro_doc(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create([
            'nro_doc' => null,
            'email' => 'victim-'.uniqid('', true).'@example.com',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/users/'.$target->id.'/ban-and-anonymize', [
            'note' => 'Fraud pattern',
        ])->assertOk();

        $this->assertDatabaseHas('banned_users', [
            'user_id' => $target->id,
            'nro_doc' => null,
        ]);

        $note = BannedUser::query()->where('user_id', $target->id)->value('note');
        $this->assertStringContainsString('Fraud pattern', (string) $note);
        $this->assertStringContainsString('user_id: '.$target->id, (string) $note);
        $this->assertStringContainsString($target->email, (string) $note);

        $this->assertSame('Usuario anónimo', $target->fresh()->name);
    }

    public function test_index_default_per_page_is_thirty(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->getJson('api/admin/users')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 30);
    }

    public function test_index_per_page_zero_clamps_to_one(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->getJson('api/admin/users?per_page=0')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 1);
    }

    public function test_index_sort_last_connection_orders_newest_first_when_desc(): void
    {
        $admin = $this->admin();
        $token = 'lc-'.uniqid('', true);
        $older = User::factory()->create([
            'name' => 'Old '.$token,
            'last_connection' => now()->subYears(1),
        ]);
        $newer = User::factory()->create([
            'name' => 'New '.$token,
            'last_connection' => now(),
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $data = $this->getJson(
            'api/admin/users?name='.urlencode($token).'&sort=last_connection&direction=desc&per_page=50'
        )->assertOk()->json('data');

        $ids = collect($data)->pluck('id')->all();
        $this->assertSame($newer->id, $ids[0]);
        $this->assertContains($older->id, $ids);
    }

    public function test_index_name_search_finds_user_by_nro_doc_fragment(): void
    {
        $admin = $this->admin();
        $needle = 'NDOC'.str_replace('.', '', uniqid('', true));
        $hit = User::factory()->create([
            'name' => 'Doc Search Person',
            'nro_doc' => $needle,
        ]);
        User::factory()->create(['name' => 'Other Person', 'nro_doc' => '99999999']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $ids = collect($this->getJson('api/admin/users?name='.urlencode(substr($needle, 0, 12)).'&per_page=50')->json('data'))
            ->pluck('id')
            ->all();

        $this->assertContains($hit->id, $ids);
    }

    public function test_index_name_search_finds_user_by_mobile_phone_fragment(): void
    {
        $admin = $this->admin();
        $digits = '54911'.substr(preg_replace('/\D/', '', uniqid('', true)), 0, 8);
        $hit = User::factory()->create([
            'name' => 'Phone Search Person',
            'mobile_phone' => '+'.$digits,
        ]);
        User::factory()->create(['name' => 'Other Person', 'mobile_phone' => '+5490000000001']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $needle = substr($digits, -8);
        $ids = collect($this->getJson('api/admin/users?name='.urlencode($needle).'&per_page=50')->json('data'))
            ->pluck('id')
            ->all();

        $this->assertContains($hit->id, $ids);
    }

    public function test_index_invalid_direction_defaults_to_desc_for_name_sort(): void
    {
        $admin = $this->admin();
        $token = 'dir-'.uniqid('', true);
        User::factory()->create(['name' => 'Alice '.$token, 'email' => 'a-'.$token.'@example.com']);
        User::factory()->create(['name' => 'Bob '.$token, 'email' => 'b-'.$token.'@example.com']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $idsDesc = collect($this->getJson(
            'api/admin/users?name='.urlencode($token).'&sort=name&direction=desc&per_page=50'
        )->json('data'))->pluck('id')->all();

        $idsWeird = collect($this->getJson(
            'api/admin/users?name='.urlencode($token).'&sort=name&direction=not-a-direction&per_page=50'
        )->json('data'))->pluck('id')->all();

        $this->assertSame($idsDesc, $idsWeird);
        $this->assertNotEmpty($idsDesc);
    }

    public function test_account_delete_list_returns_data_envelope(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        DeleteAccountRequest::query()->create([
            'user_id' => $owner->id,
            'date_requested' => now()->subHour(),
            'action_taken' => DeleteAccountRequest::ACTION_REQUESTED,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->getJson('api/admin/users/account-delete-list')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_account_delete_update_response_includes_user_email(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create(['email' => 'acct-'.uniqid('', true).'@example.com']);
        $row = DeleteAccountRequest::query()->create([
            'user_id' => $owner->id,
            'date_requested' => now()->subDay(),
            'action_taken' => DeleteAccountRequest::ACTION_REQUESTED,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/users/account-delete-update', [
            'id' => $row->id,
            'action_taken' => DeleteAccountRequest::ACTION_REJECTED,
        ])
            ->assertOk()
            ->assertJsonPath('data.user.email', $owner->email)
            ->assertJsonPath('data.action_taken', DeleteAccountRequest::ACTION_REJECTED);
    }

    public function test_clear_identity_validation_returns_stable_data_keys(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create([
            'name' => 'Identity Target',
            'nro_doc' => '30111222',
            'identity_validated' => true,
            'identity_validated_at' => now(),
            'identity_validation_type' => 'mercado_pago',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/users/'.$target->id.'/clear-identity-validation')
            ->assertOk()
            ->assertJsonPath('data.id', $target->id)
            ->assertJsonPath('data.name', 'Identity Target')
            ->assertJsonPath('data.nro_doc', '30111222')
            ->assertJsonPath('data.identity_validated', false)
            ->assertJsonPath('data.identity_validated_at', null)
            ->assertJsonPath('data.identity_validation_type', null);
    }

    public function test_banned_users_list_returns_paginated_payload(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        BannedUser::query()->create([
            'user_id' => $target->id,
            'nro_doc' => '30123456',
            'banned_at' => now(),
            'banned_by' => $admin->id,
            'note' => 'fixture',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->getJson('api/admin/banned-users?per_page=10')
            ->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'per_page']);
    }

    public function test_anonymize_succeeds_when_user_has_no_ratings(): void
    {
        $this->spy(DeviceManager::class);

        $admin = $this->admin();
        $target = User::factory()->create(['name' => 'To Wipe', 'email' => 'wipe-'.uniqid('', true).'@example.com']);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/users/'.$target->id.'/anonymize')
            ->assertOk()
            ->assertJsonPath('action', 'anonymized');

        $this->assertDatabaseHas('admin_action_logs', [
            'admin_user_id' => $admin->id,
            'action' => AdminActionLog::ACTION_USER_ANONYMIZE,
            'target_user_id' => $target->id,
        ]);

        app(DeviceManager::class)->shouldHaveReceived('logoutAllDevices')->once();
        $this->assertSame('Usuario anónimo', $target->fresh()->name);
    }

    public function test_anonymize_returns_unprocessable_when_user_has_negative_received_rating(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['name' => 'Negative Rated']);
        $other = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $other->id]);

        Rating::factory()->create([
            'trip_id' => $trip->id,
            'user_id_from' => $other->id,
            'user_id_to' => $target->id,
            'rating' => Rating::STATE_NEGATIVO,
            'comment' => 'bad',
            'reply_comment' => '',
            'voted' => 1,
            'voted_hash' => 'h'.uniqid(),
            'rate_at' => now(),
            'available' => 1,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/users/'.$target->id.'/anonymize')
            ->assertUnprocessable()
            ->assertJsonPath('error', 'requires_ban');
    }

    public function test_ban_and_anonymize_stores_digits_only_nro_doc_when_present(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create([
            'nro_doc' => '30.123.456',
            'email' => 'banned-'.uniqid('', true).'@example.com',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/users/'.$target->id.'/ban-and-anonymize', [
            'note' => 'Spam',
        ])->assertOk();

        $this->assertDatabaseHas('banned_users', [
            'user_id' => $target->id,
            'nro_doc' => '30123456',
        ]);
    }

    public function test_ban_and_anonymize_with_blank_note_and_doc_only_stores_note_null(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create([
            'nro_doc' => '11222333',
            'email' => 'noteblank-'.uniqid('', true).'@example.com',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/users/'.$target->id.'/ban-and-anonymize', [
            'note' => '   ',
        ])->assertOk();

        $row = BannedUser::query()->where('user_id', $target->id)->first();
        $this->assertNotNull($row);
        $this->assertNull($row->note);
    }

    public function test_ban_and_anonymize_logs_admin_action_with_note_detail(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create([
            'nro_doc' => '44555666',
            'email' => 'logban-'.uniqid('', true).'@example.com',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/users/'.$target->id.'/ban-and-anonymize', [
            'note' => 'Repeat offender',
        ])->assertOk();

        $this->assertDatabaseHas('admin_action_logs', [
            'admin_user_id' => $admin->id,
            'action' => AdminActionLog::ACTION_USER_BAN_AND_ANONYMIZE,
            'target_user_id' => $target->id,
        ]);

        $details = AdminActionLog::query()
            ->where('target_user_id', $target->id)
            ->where('action', AdminActionLog::ACTION_USER_BAN_AND_ANONYMIZE)
            ->value('details');

        $this->assertIsArray($details);
        $this->assertSame('Repeat offender', $details['note'] ?? null);
    }
}
