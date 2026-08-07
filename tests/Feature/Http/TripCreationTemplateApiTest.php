<?php

namespace Tests\Feature\Http;

use STS\Models\TripCreationTemplate;
use STS\Models\User;
use Tests\TestCase;

class TripCreationTemplateApiTest extends TestCase
{
    private function templatePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Rosario a BA',
            'data' => [
                'trip' => ['is_passenger' => 0, 'total_seats' => 3],
                'points' => [
                    ['name' => 'Rosario'],
                    ['name' => 'Buenos Aires'],
                ],
                'date' => '',
                'dateAnswer' => '',
                'time' => '',
            ],
        ], $overrides);
    }

    public function test_trip_creation_template_routes_require_authentication(): void
    {
        $this->getJson('api/trip-creation-templates')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized.']);

        $this->postJson('api/trip-creation-templates', $this->templatePayload())
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_index_returns_empty_list_when_user_has_no_templates(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $this->actingAs($user, 'api');

        $this->getJson('api/trip-creation-templates')
            ->assertOk()
            ->assertJson(['data' => []]);
    }

    public function test_store_creates_template_for_authenticated_user(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $this->actingAs($user, 'api');

        $response = $this->postJson('api/trip-creation-templates', $this->templatePayload());
        $response->assertOk();
        $response->assertJsonPath('data.name', 'Rosario a BA');
        $response->assertJsonPath('data.data.trip.total_seats', 3);

        $this->assertDatabaseHas('trip_creation_templates', [
            'user_id' => $user->id,
            'name' => 'Rosario a BA',
        ]);
    }

    public function test_store_upserts_template_with_same_name_for_user(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $this->actingAs($user, 'api');

        $this->postJson('api/trip-creation-templates', $this->templatePayload([
            'data' => ['trip' => ['total_seats' => 2]],
        ]))->assertOk();

        $this->postJson('api/trip-creation-templates', $this->templatePayload([
            'data' => ['trip' => ['total_seats' => 4]],
        ]))
            ->assertOk()
            ->assertJsonPath('data.data.trip.total_seats', 4);

        $this->assertSame(1, TripCreationTemplate::where('user_id', $user->id)->count());
    }

    public function test_index_lists_only_current_user_templates(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $other = User::factory()->create(['active' => true, 'banned' => false]);

        TripCreationTemplate::create([
            'user_id' => $user->id,
            'name' => 'Semanal',
            'data' => ['trip' => ['total_seats' => 2]],
        ]);
        TripCreationTemplate::create([
            'user_id' => $other->id,
            'name' => 'Otro',
            'data' => ['trip' => ['total_seats' => 5]],
        ]);

        $this->actingAs($user, 'api');

        $response = $this->getJson('api/trip-creation-templates')->assertOk();
        $payload = $response->json('data');
        $this->assertCount(1, $payload);
        $this->assertSame('Semanal', $payload[0]['name']);
    }

    public function test_show_returns_named_template_for_current_user(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        TripCreationTemplate::create([
            'user_id' => $user->id,
            'name' => 'Fin de semana',
            'data' => ['trip' => ['total_seats' => 3]],
        ]);

        $this->actingAs($user, 'api');

        $this->getJson('api/trip-creation-templates/Fin%20de%20semana')
            ->assertOk()
            ->assertJsonPath('data.name', 'Fin de semana')
            ->assertJsonPath('data.data.trip.total_seats', 3);
    }
}
