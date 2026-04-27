<?php

namespace Tests\Feature\Http;

use STS\Models\Badge;
use STS\Models\User;
use Tests\TestCase;

/**
 * Asserts {@see \STS\Http\Resources\UserBadgeResource} via {@see \STS\Http\Controllers\Api\v1\UserController::badges}.
 */
class UserBadgeResourceTest extends TestCase
{
    public function test_me_badges_returns_minimal_public_shape(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $badge = Badge::query()->create([
            'title' => 'Public title',
            'slug' => 'ub-me-'.uniqid(),
            'description' => 'Shown on profile',
            'image_path' => 'badges/me.png',
            'rules' => ['type' => 'monthly_donor'],
            'visible' => true,
        ]);
        $badge->users()->attach($user->id, ['awarded_at' => now()]);

        $response = $this->getJson('api/users/me/badges');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'title',
                    'description',
                    'image_path',
                ],
            ],
        ]);

        $row = $response->json('data.0');
        $this->assertSame($badge->id, $row['id']);
        $this->assertSame('Public title', $row['title']);
        $this->assertSame('Shown on profile', $row['description']);
        $this->assertSame('badges/me.png', $row['image_path']);
        $this->assertArrayNotHasKey('slug', $row);
        $this->assertArrayNotHasKey('rules', $row);
        $this->assertArrayNotHasKey('users_count', $row);
    }

    public function test_only_visible_badges_are_listed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $visible = Badge::query()->create([
            'title' => 'Visible',
            'slug' => 'ub-vis-'.uniqid(),
            'description' => null,
            'image_path' => null,
            'rules' => ['type' => 'carpoolear_member'],
            'visible' => true,
        ]);
        $hidden = Badge::query()->create([
            'title' => 'Hidden',
            'slug' => 'ub-hid-'.uniqid(),
            'description' => 'Secret',
            'image_path' => null,
            'rules' => ['type' => 'monthly_donor'],
            'visible' => false,
        ]);

        $visible->users()->attach($user->id, ['awarded_at' => now()]);
        $hidden->users()->attach($user->id, ['awarded_at' => now()]);

        $response = $this->getJson('api/users/me/badges');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertSame($visible->id, $response->json('data.0.id'));
        $this->assertSame('Visible', $response->json('data.0.title'));
    }

    public function test_authenticated_user_can_fetch_another_users_visible_badges(): void
    {
        $viewer = User::factory()->create();
        $owner = User::factory()->create();
        $this->actingAs($viewer, 'api');

        $badge = Badge::query()->create([
            'title' => 'Owner badge',
            'slug' => 'ub-owner-'.uniqid(),
            'description' => 'For owner',
            'image_path' => null,
            'rules' => ['type' => 'monthly_donor'],
            'visible' => true,
        ]);
        $badge->users()->attach($owner->id, ['awarded_at' => now()]);

        $response = $this->getJson("api/users/{$owner->id}/badges");
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Owner badge');
    }

    public function test_unknown_user_returns_422(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $missingId = User::query()->max('id') + 5000;

        $this->getJson("api/users/{$missingId}/badges")
            ->assertStatus(422)
            ->assertJsonPath('message', 'User not found.');
    }

    public function test_no_badges_returns_empty_data_array(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $this->getJson('api/users/me/badges')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
