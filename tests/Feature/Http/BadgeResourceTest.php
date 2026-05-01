<?php

namespace Tests\Feature\Http;

use STS\Models\Badge;
use STS\Models\User;
use Tests\TestCase;

/**
 * Asserts {@see \STS\Http\Resources\BadgeResource} payload via admin badge HTTP routes.
 */
class BadgeResourceTest extends TestCase
{
    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->saveQuietly();

        return $user->fresh();
    }

    public function test_index_returns_badge_resource_shape_with_users_count(): void
    {
        $this->actingAs($this->admin(), 'api');
        $this->withoutMiddleware(\STS\Http\Middleware\UserAdmin::class);

        $slug = 'resource-index-'.uniqid();
        $this->postJson('api/admin/badges', [
            'title' => 'Listed badge',
            'slug' => $slug,
            'description' => 'Desc',
            'image_path' => 'badges/x.png',
            'rules' => [
                'type' => 'monthly_donor',
            ],
        ])->assertCreated();

        $response = $this->getJson('api/admin/badges');
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'slug',
                    'description',
                    'image_path',
                    'rules',
                    'created_at',
                    'updated_at',
                    'users_count',
                ],
            ],
        ]);

        $row = collect($response->json('data'))->firstWhere('slug', $slug);
        $this->assertNotNull($row);
        $this->assertSame('Listed badge', $row['title']);
        $this->assertSame('Desc', $row['description']);
        $this->assertSame('badges/x.png', $row['image_path']);
        $this->assertSame('monthly_donor', $row['rules']['type']);
        $this->assertSame(0, $row['users_count']);
    }

    public function test_index_users_count_matches_attached_users(): void
    {
        $this->actingAs($this->admin(), 'api');
        $this->withoutMiddleware(\STS\Http\Middleware\UserAdmin::class);

        $slug = 'resource-count-'.uniqid();
        $created = $this->postJson('api/admin/badges', [
            'title' => 'Counted',
            'slug' => $slug,
            'rules' => [
                'type' => 'monthly_donor',
            ],
        ]);
        $created->assertCreated();
        $badgeId = (int) $created->json('data.id');

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $badge = Badge::query()->findOrFail($badgeId);
        $badge->users()->attach([
            $u1->id => ['awarded_at' => now()],
            $u2->id => ['awarded_at' => now()],
        ]);

        $row = collect($this->getJson('api/admin/badges')->json('data'))
            ->firstWhere('id', $badgeId);
        $this->assertNotNull($row);
        $this->assertSame(2, $row['users_count']);
    }

    public function test_show_includes_users_count_after_load_count(): void
    {
        $this->actingAs($this->admin(), 'api');
        $this->withoutMiddleware(\STS\Http\Middleware\UserAdmin::class);

        $slug = 'resource-show-'.uniqid();
        $created = $this->postJson('api/admin/badges', [
            'title' => 'Single',
            'slug' => $slug,
            'rules' => [
                'type' => 'registration_duration',
                'days' => 7,
            ],
        ]);
        $created->assertCreated();
        $id = (int) $created->json('data.id');

        $user = User::factory()->create();
        Badge::query()->findOrFail($id)->users()->attach($user->id, ['awarded_at' => now()]);

        $response = $this->getJson("api/admin/badges/{$id}");
        $response->assertOk();
        $response->assertJsonPath('data.id', $id);
        $response->assertJsonPath('data.slug', $slug);
        $response->assertJsonPath('data.rules.type', 'registration_duration');
        $response->assertJsonPath('data.rules.days', 7);
        $response->assertJsonPath('data.users_count', 1);
    }

    public function test_store_and_update_responses_omit_users_count_without_with_count(): void
    {
        $this->actingAs($this->admin(), 'api');
        $this->withoutMiddleware(\STS\Http\Middleware\UserAdmin::class);

        $slug = 'resource-store-'.uniqid();
        $store = $this->postJson('api/admin/badges', [
            'title' => 'New',
            'slug' => $slug,
            'rules' => [
                'type' => 'monthly_donor',
            ],
        ]);
        $store->assertCreated();
        $data = $store->json('data');
        $this->assertIsArray($data);
        $this->assertArrayNotHasKey('users_count', $data);

        $id = (int) $data['id'];
        $update = $this->putJson("api/admin/badges/{$id}", [
            'title' => 'Renamed',
            'slug' => $slug,
            'rules' => [
                'type' => 'monthly_donor',
            ],
        ]);
        $update->assertOk();
        $this->assertArrayNotHasKey('users_count', $update->json('data'));
        $this->assertSame('Renamed', $update->json('data.title'));
    }

    public function test_store_nullable_description_and_image_path_are_json_null(): void
    {
        $this->actingAs($this->admin(), 'api');
        $this->withoutMiddleware(\STS\Http\Middleware\UserAdmin::class);

        $slug = 'resource-nulls-'.uniqid();
        $response = $this->postJson('api/admin/badges', [
            'title' => 'Minimal fields',
            'slug' => $slug,
            'rules' => [
                'type' => 'carpoolear_member',
            ],
        ]);
        $response->assertCreated();
        $response->assertJsonPath('data.description', null);
        $response->assertJsonPath('data.image_path', null);
        $response->assertJsonPath('data.rules.type', 'carpoolear_member');
    }

    public function test_destroy_returns_no_content_and_removes_badge(): void
    {
        $this->actingAs($this->admin(), 'api');
        $this->withoutMiddleware(\STS\Http\Middleware\UserAdmin::class);

        $slug = 'resource-delete-'.uniqid();
        $created = $this->postJson('api/admin/badges', [
            'title' => 'To remove',
            'slug' => $slug,
            'rules' => [
                'type' => 'monthly_donor',
            ],
        ])->assertCreated();

        $id = (int) $created->json('data.id');

        $this->deleteJson("api/admin/badges/{$id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('badges', ['id' => $id]);
    }
}
