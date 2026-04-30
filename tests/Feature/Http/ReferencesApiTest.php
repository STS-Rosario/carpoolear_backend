<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use STS\Models\References;
use STS\Models\User;
use Tests\TestCase;

class ReferencesApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_create_requires_authentication(): void
    {
        $this->postJson('api/references', [
            'comment' => 'Great experience.',
            'user_id_to' => 1,
        ])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_create_returns_reference_payload_when_valid(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create();

        $response = $this->actingAs($from, 'api')
            ->postJson('api/references', [
                'comment' => 'Reliable and punctual.',
                'user_id_to' => $to->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('comment', 'Reliable and punctual.')
            ->assertJsonPath('user_id_from', $from->id)
            ->assertJsonPath('user_id_to', $to->id)
            ->assertJsonStructure(['id', 'user_id_from', 'user_id_to', 'comment']);

        $this->assertDatabaseHas('users_references', [
            'user_id_from' => $from->id,
            'user_id_to' => $to->id,
            'comment' => 'Reliable and punctual.',
        ]);
    }

    public function test_create_returns_unprocessable_when_comment_missing(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create();

        $this->actingAs($from, 'api')
            ->postJson('api/references', [
                'user_id_to' => $to->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Could not rate user.')
            ->assertJsonStructure(['errors']);
    }

    public function test_create_returns_unprocessable_when_target_user_does_not_exist(): void
    {
        $from = User::factory()->create();
        $missingId = (int) (User::query()->max('id') ?? 0) + 99_999;

        $this->actingAs($from, 'api')
            ->postJson('api/references', [
                'comment' => 'They were great.',
                'user_id_to' => $missingId,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Could not rate user.');
    }

    public function test_create_returns_unprocessable_when_author_targets_self(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->postJson('api/references', [
                'comment' => 'Self reference attempt.',
                'user_id_to' => $user->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Could not rate user.');
    }

    public function test_create_returns_unprocessable_when_reference_already_exists(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create();

        References::query()->create([
            'user_id_from' => $from->id,
            'user_id_to' => $to->id,
            'comment' => 'First reference.',
        ]);

        $this->actingAs($from, 'api')
            ->postJson('api/references', [
                'comment' => 'Second attempt.',
                'user_id_to' => $to->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Could not rate user.');
    }
}
