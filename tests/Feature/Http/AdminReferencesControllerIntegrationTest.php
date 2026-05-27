<?php

namespace Tests\Feature\Http;

use STS\Http\Middleware\UserAdmin;
use STS\Models\AdminActionLog;
use STS\Models\References;
use STS\Models\User;
use Tests\TestCase;

class AdminReferencesControllerIntegrationTest extends TestCase
{
    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->saveQuietly();

        return $user->fresh();
    }

    public function test_update_requires_admin(): void
    {
        $user = User::factory()->create();
        $from = User::factory()->create();
        $to = User::factory()->create();
        $reference = References::query()->create([
            'user_id_from' => $from->id,
            'user_id_to' => $to->id,
            'comment' => 'Original',
        ]);

        $this->actingAs($user, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->patchJson('api/admin/references/'.$reference->id, [
            'comment' => 'Updated',
        ])->assertUnauthorized();
    }

    public function test_admin_update_persists_comment_and_logs_action(): void
    {
        $admin = $this->admin();
        $from = User::factory()->create();
        $to = User::factory()->create();
        $reference = References::query()->create([
            'user_id_from' => $from->id,
            'user_id_to' => $to->id,
            'comment' => 'Original reference',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->patchJson('api/admin/references/'.$reference->id, [
            'comment' => 'Updated reference',
        ])
            ->assertOk()
            ->assertJsonPath('data.comment', 'Updated reference')
            ->assertJsonPath('data.id', $reference->id);

        $this->assertDatabaseHas('users_references', [
            'id' => $reference->id,
            'comment' => 'Updated reference',
        ]);

        $log = AdminActionLog::query()
            ->where('admin_user_id', $admin->id)
            ->where('action', AdminActionLog::ACTION_REFERENCE_UPDATE)
            ->where('target_user_id', $to->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('reference', $log->details['entity_type']);
        $this->assertSame($reference->id, $log->details['entity_id']);
        $this->assertSame('Original reference', $log->details['before']['comment']);
        $this->assertSame('Updated reference', $log->details['after']['comment']);
    }

    public function test_update_returns_unprocessable_when_comment_missing(): void
    {
        $admin = $this->admin();
        $from = User::factory()->create();
        $to = User::factory()->create();
        $reference = References::query()->create([
            'user_id_from' => $from->id,
            'user_id_to' => $to->id,
            'comment' => 'Original',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->patchJson('api/admin/references/'.$reference->id, [])
            ->assertUnprocessable();
    }

    public function test_update_returns_not_found_for_missing_reference(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->patchJson('api/admin/references/999999999', [
            'comment' => 'Nope',
        ])->assertNotFound();
    }
}
