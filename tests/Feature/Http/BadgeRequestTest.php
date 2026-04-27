<?php

namespace Tests\Feature\Http;

use STS\Models\Campaign;
use STS\Models\User;
use Tests\TestCase;

/**
 * Exercises {@see \STS\Http\Requests\BadgeRequest} via admin badge routes.
 */
class BadgeRequestTest extends TestCase
{
    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->saveQuietly();

        return $user->fresh();
    }

    private function nonAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => false])->saveQuietly();

        return $user->fresh();
    }

    public function test_non_admin_cannot_create_badge(): void
    {
        $this->actingAs($this->nonAdmin(), 'api');
        $this->withoutMiddleware(\STS\Http\Middleware\UserAdmin::class);

        $this->postJson('api/admin/badges', $this->validRegistrationDurationPayload('slug-non-admin'))
            ->assertForbidden();
    }

    public function test_store_requires_title(): void
    {
        $this->actingAs($this->admin(), 'api');
        $this->withoutMiddleware(\STS\Http\Middleware\UserAdmin::class);

        $payload = $this->validRegistrationDurationPayload('slug-no-title');
        unset($payload['title']);

        $this->postJson('api/admin/badges', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_store_requires_rules_type_in_allowed_list(): void
    {
        $this->actingAs($this->admin(), 'api');
        $this->withoutMiddleware(\STS\Http\Middleware\UserAdmin::class);

        $payload = $this->validRegistrationDurationPayload('slug-bad-type');
        $payload['rules']['type'] = 'not_a_real_badge_type';

        $response = $this->postJson('api/admin/badges', $payload);
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['rules.type']);

        $errors = $response->json('errors');
        $message = (string) (is_array($errors['rules.type'] ?? null)
            ? ($errors['rules.type'][0] ?? '')
            : ($errors['rules.type'] ?? ''));
        $this->assertStringContainsString('badge type', strtolower($message));
    }

    public function test_store_registration_duration_requires_days(): void
    {
        $this->actingAs($this->admin(), 'api');
        $this->withoutMiddleware(\STS\Http\Middleware\UserAdmin::class);

        $payload = [
            'title' => 'Duration badge',
            'slug' => 'slug-no-days-'.uniqid(),
            'rules' => [
                'type' => 'registration_duration',
            ],
        ];

        $this->postJson('api/admin/badges', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rules.days']);
    }

    public function test_store_donated_to_campaign_requires_existing_campaign(): void
    {
        $this->actingAs($this->admin(), 'api');
        $this->withoutMiddleware(\STS\Http\Middleware\UserAdmin::class);

        $payload = [
            'title' => 'Campaign donor badge',
            'slug' => 'slug-campaign-'.uniqid(),
            'rules' => [
                'type' => 'donated_to_campaign',
            ],
        ];

        $this->postJson('api/admin/badges', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rules.campaign_id']);
    }

    public function test_store_donated_to_campaign_succeeds_when_campaign_exists(): void
    {
        $this->actingAs($this->admin(), 'api');
        $this->withoutMiddleware(\STS\Http\Middleware\UserAdmin::class);

        $campaign = Campaign::query()->create([
            'slug' => 'camp-'.uniqid(),
            'title' => 'Campaign',
            'description' => 'Desc',
            'start_date' => now()->toDateString(),
        ]);

        $slug = 'slug-donor-'.uniqid();
        $response = $this->postJson('api/admin/badges', [
            'title' => 'Donor badge',
            'slug' => $slug,
            'rules' => [
                'type' => 'donated_to_campaign',
                'campaign_id' => $campaign->id,
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('badges', [
            'slug' => $slug,
        ]);
    }

    public function test_store_total_donated_requires_amount(): void
    {
        $this->actingAs($this->admin(), 'api');
        $this->withoutMiddleware(\STS\Http\Middleware\UserAdmin::class);

        $payload = [
            'title' => 'Total donated badge',
            'slug' => 'slug-total-'.uniqid(),
            'rules' => [
                'type' => 'total_donated',
            ],
        ];

        $this->postJson('api/admin/badges', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rules.amount']);
    }

    public function test_store_total_donated_accepts_zero_amount(): void
    {
        $this->actingAs($this->admin(), 'api');
        $this->withoutMiddleware(\STS\Http\Middleware\UserAdmin::class);

        $slug = 'slug-total-zero-'.uniqid();
        $this->postJson('api/admin/badges', [
            'title' => 'Total donated',
            'slug' => $slug,
            'rules' => [
                'type' => 'total_donated',
                'amount' => 0,
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('badges', ['slug' => $slug]);
    }

    public function test_store_rejects_duplicate_slug(): void
    {
        $this->actingAs($this->admin(), 'api');
        $this->withoutMiddleware(\STS\Http\Middleware\UserAdmin::class);

        $slug = 'duplicate-slug-'.uniqid();
        $this->postJson('api/admin/badges', $this->validRegistrationDurationPayload($slug))
            ->assertCreated();

        $this->postJson('api/admin/badges', $this->validRegistrationDurationPayload($slug))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_update_allows_same_slug_for_same_badge(): void
    {
        $this->actingAs($this->admin(), 'api');
        $this->withoutMiddleware(\STS\Http\Middleware\UserAdmin::class);

        $slug = 'same-slug-update-'.uniqid();
        $create = $this->postJson('api/admin/badges', $this->validRegistrationDurationPayload($slug));
        $create->assertCreated();
        $id = (int) $create->json('data.id');

        $this->putJson("api/admin/badges/{$id}", [
            'title' => 'Updated title',
            'slug' => $slug,
            'description' => 'New description',
            'rules' => [
                'type' => 'registration_duration',
                'days' => 14,
            ],
        ])->assertOk();

        $this->assertDatabaseHas('badges', [
            'id' => $id,
            'slug' => $slug,
            'title' => 'Updated title',
        ]);
    }

    public function test_update_rejects_slug_owned_by_another_badge(): void
    {
        $this->actingAs($this->admin(), 'api');
        $this->withoutMiddleware(\STS\Http\Middleware\UserAdmin::class);

        $slugA = 'slug-a-'.uniqid();
        $slugB = 'slug-b-'.uniqid();

        $this->postJson('api/admin/badges', $this->validRegistrationDurationPayload($slugA))->assertCreated();
        $second = $this->postJson('api/admin/badges', $this->validRegistrationDurationPayload($slugB));
        $second->assertCreated();
        $idB = (int) $second->json('data.id');

        $this->putJson("api/admin/badges/{$idB}", [
            'title' => 'Steal slug',
            'slug' => $slugA,
            'rules' => [
                'type' => 'registration_duration',
                'days' => 3,
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_store_monthly_donor_rules_without_extra_fields(): void
    {
        $this->actingAs($this->admin(), 'api');
        $this->withoutMiddleware(\STS\Http\Middleware\UserAdmin::class);

        $slug = 'monthly-donor-'.uniqid();
        $this->postJson('api/admin/badges', [
            'title' => 'Monthly',
            'slug' => $slug,
            'rules' => [
                'type' => 'monthly_donor',
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('badges', ['slug' => $slug]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validRegistrationDurationPayload(string $slug): array
    {
        return [
            'title' => 'Test badge '.$slug,
            'slug' => $slug,
            'description' => 'Optional description',
            'rules' => [
                'type' => 'registration_duration',
                'days' => 30,
            ],
        ];
    }
}
