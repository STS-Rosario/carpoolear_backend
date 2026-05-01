<?php

namespace Tests\Feature\Http;

use STS\Http\Middleware\UserAdmin;
use STS\Models\AdminActionLog;
use STS\Models\BannedUser;
use STS\Models\DeleteAccountRequest;
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
}
