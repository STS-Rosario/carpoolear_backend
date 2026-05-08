<?php

namespace Tests\Feature\Http;

use STS\Http\Middleware\UserAdmin;
use STS\Models\AdminActionLog;
use STS\Models\Trip;
use STS\Models\User;
use STS\Models\UserMigration;
use STS\Services\Logic\DeviceManager;
use STS\Services\UserDeletionService;
use Tests\TestCase;

class AdminUserMigrationControllerIntegrationTest extends TestCase
{
    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->saveQuietly();

        return $user->fresh();
    }

    public function test_index_returns_user_migrations_newest_first_with_admin(): void
    {
        $admin = $this->admin();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $u3 = User::factory()->create();
        $u4 = User::factory()->create();

        UserMigration::query()->create([
            'admin_user_id' => $admin->id,
            'user_id_kept' => $u1->id,
            'user_id_removed' => $u2->id,
        ]);
        UserMigration::query()->create([
            'admin_user_id' => $admin->id,
            'user_id_kept' => $u3->id,
            'user_id_removed' => $u4->id,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->getJson('api/admin/user-migrations?per_page=10');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(2, count($data));
        $first = $data[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('user_id_kept', $first);
        $this->assertArrayHasKey('user_id_removed', $first);
        $this->assertArrayHasKey('admin', $first);
        $this->assertSame($admin->id, $first['admin']['id']);
        $newest = UserMigration::query()->orderByDesc('id')->first();
        $this->assertSame($newest->user_id_kept, (int) $first['user_id_kept']);
    }

    public function test_store_rejects_when_kept_and_removed_are_equal(): void
    {
        $admin = $this->admin();
        $u = User::factory()->create();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/user-migrations', [
            'user_id_kept' => $u->id,
            'user_id_removed' => $u->id,
        ])->assertUnprocessable();
    }

    public function test_store_rejects_when_user_does_not_exist(): void
    {
        $admin = $this->admin();
        $u = User::factory()->create();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/user-migrations', [
            'user_id_kept' => $u->id,
            'user_id_removed' => 999_999_998,
        ])->assertUnprocessable();
    }

    public function test_store_runs_user_update_artisan_and_persists_migration_row(): void
    {
        $admin = $this->admin();
        $kept = User::factory()->create();
        $removed = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $removed->id]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->postJson('api/admin/user-migrations', [
            'user_id_kept' => $kept->id,
            'user_id_removed' => $removed->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.user_id_kept', $kept->id);
        $response->assertJsonPath('data.user_id_removed', $removed->id);

        $this->assertSame($kept->id, (int) $trip->fresh()->user_id);

        $this->assertDatabaseHas('user_migrations', [
            'admin_user_id' => $admin->id,
            'user_id_kept' => $kept->id,
            'user_id_removed' => $removed->id,
        ]);
    }

    public function test_store_deletes_removed_user_when_deletion_succeeds(): void
    {
        $this->spy(DeviceManager::class);

        $admin = $this->admin();
        $kept = User::factory()->create();
        $removed = User::factory()->create();
        Trip::factory()->create(['user_id' => $removed->id]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->postJson('api/admin/user-migrations', [
            'user_id_kept' => $kept->id,
            'user_id_removed' => $removed->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.removal_action', 'deleted');

        $this->assertDatabaseMissing('users', ['id' => $removed->id]);

        $this->assertDatabaseHas('admin_action_logs', [
            'admin_user_id' => $admin->id,
            'action' => AdminActionLog::ACTION_USER_DELETE,
            'target_user_id' => $removed->id,
        ]);

        app(DeviceManager::class)->shouldHaveReceived('logoutAllDevices')->once();
    }

    public function test_store_falls_back_to_anonymize_when_deletion_throws(): void
    {
        $this->spy(DeviceManager::class);

        $this->mock(UserDeletionService::class, function ($mock) {
            $mock->shouldReceive('deleteUser')->andThrow(new \RuntimeException('cannot delete: foreign key constraint'));
        });

        $admin = $this->admin();
        $kept = User::factory()->create();
        $removed = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.test',
        ]);
        Trip::factory()->create(['user_id' => $removed->id]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->postJson('api/admin/user-migrations', [
            'user_id_kept' => $kept->id,
            'user_id_removed' => $removed->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.removal_action', 'anonymized');

        $this->assertDatabaseHas('users', [
            'id' => $removed->id,
            'name' => 'Usuario anónimo',
            'active' => 0,
        ]);
        $this->assertDatabaseMissing('users', [
            'id' => $removed->id,
            'email' => 'original@example.test',
        ]);

        $this->assertDatabaseHas('admin_action_logs', [
            'admin_user_id' => $admin->id,
            'action' => AdminActionLog::ACTION_USER_ANONYMIZE,
            'target_user_id' => $removed->id,
        ]);

        app(DeviceManager::class)->shouldHaveReceived('logoutAllDevices')->once();
    }
}
