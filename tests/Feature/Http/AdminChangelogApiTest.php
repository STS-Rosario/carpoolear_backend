<?php

namespace Tests\Feature\Http;

use STS\Http\Middleware\UserAdmin;
use STS\Models\Changelog;
use STS\Models\User;
use Tests\TestCase;

class AdminChangelogApiTest extends TestCase
{
    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->saveQuietly();

        return $user->fresh();
    }

    public function test_admin_lists_changelogs_newest_first(): void
    {
        $admin = $this->adminUser();
        $older = Changelog::create([
            'version' => '1.0.0',
            'body_markdown' => 'Old notes',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $newer = Changelog::create([
            'version' => '2.0.0',
            'body_markdown' => 'New notes',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->getJson('api/admin/changelogs')->assertOk();
        $rows = collect($response->json('data'));
        $newerIdx = $rows->search(fn (array $r): bool => (int) $r['id'] === $newer->id);
        $olderIdx = $rows->search(fn (array $r): bool => (int) $r['id'] === $older->id);
        $this->assertNotFalse($newerIdx);
        $this->assertNotFalse($olderIdx);
        $this->assertLessThan($olderIdx, $newerIdx);
    }

    public function test_admin_stores_changelog_with_audit_fields(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->postJson('api/admin/changelogs', [
            'version' => '3.2.3',
            'body_markdown' => '## Cambios',
        ])->assertCreated();

        $data = $response->json('data');
        $this->assertSame('3.2.3', $data['version']);
        $this->assertSame('## Cambios', $data['body_markdown']);
        $this->assertSame($admin->id, $data['created_by']);
        $this->assertSame($admin->id, $data['updated_by']);

        $this->assertDatabaseHas('changelogs', [
            'id' => $data['id'],
            'version' => '3.2.3',
            'created_by' => $admin->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/changelogs', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['version', 'body_markdown']);
    }

    public function test_store_rejects_duplicate_version(): void
    {
        $admin = $this->adminUser();
        Changelog::create([
            'version' => '1.0.0',
            'body_markdown' => 'Existing',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/changelogs', [
            'version' => '1.0.0',
            'body_markdown' => 'Duplicate',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['version']);
    }

    public function test_admin_shows_changelog(): void
    {
        $admin = $this->adminUser();
        $changelog = Changelog::create([
            'version' => '4.0.0',
            'body_markdown' => 'Notes',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->getJson('api/admin/changelogs/'.$changelog->id)->assertOk();
        $data = $response->json('data');
        $this->assertSame($changelog->id, $data['id']);
        $this->assertArrayHasKey('creator', $data);
        $this->assertSame($admin->id, $data['creator']['id']);
    }

    public function test_admin_updates_changelog_sets_updated_by(): void
    {
        $admin = $this->adminUser();
        $other = $this->adminUser();
        $changelog = Changelog::create([
            'version' => '5.0.0',
            'body_markdown' => 'A',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($other, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->putJson('api/admin/changelogs/'.$changelog->id, [
            'version' => '5.0.1',
            'body_markdown' => 'B',
        ])->assertOk();

        $data = $response->json('data');
        $this->assertSame('5.0.1', $data['version']);
        $this->assertSame('B', $data['body_markdown']);
        $this->assertSame($admin->id, $data['created_by']);
        $this->assertSame($other->id, $data['updated_by']);
    }

    public function test_admin_destroys_changelog(): void
    {
        $admin = $this->adminUser();
        $changelog = Changelog::create([
            'version' => '6.0.0',
            'body_markdown' => 'Z',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->deleteJson('api/admin/changelogs/'.$changelog->id)->assertNoContent();
        $this->assertDatabaseMissing('changelogs', ['id' => $changelog->id]);
    }
}
