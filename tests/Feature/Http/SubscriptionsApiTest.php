<?php

namespace Tests\Feature\Http;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use STS\Models\Subscription;
use STS\Models\User;
use Tests\TestCase;

class SubscriptionsApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function validSubscriptionPayload(array $overrides = []): array
    {
        return array_merge([
            'trip_date' => '2027-06-15 14:00:00',
            'from_address' => 'Origin St',
            'from_lat' => -34.6,
            'from_lng' => -58.4,
            'to_address' => 'Dest Ave',
            'to_lat' => -34.7,
            'to_lng' => -58.5,
            'is_passenger' => 'false',
        ], $overrides);
    }

    public function test_subscription_endpoints_require_authentication(): void
    {
        $this->getJson('api/subscriptions')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized.']);

        $this->postJson('api/subscriptions', $this->validSubscriptionPayload())
            ->assertUnauthorized();

        $this->putJson('api/subscriptions/1', $this->validSubscriptionPayload())
            ->assertUnauthorized();

        $this->deleteJson('api/subscriptions/1')
            ->assertUnauthorized();
    }

    public function test_create_returns_persisted_subscription_in_data_wrapper(): void
    {
        Carbon::setTestNow('2027-03-01 10:00:00');
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $payload = $this->validSubscriptionPayload();

        $response = $this->postJson('api/subscriptions', $payload);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'user_id', 'from_address', 'to_address']])
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonFragment([
                'from_address' => 'Origin St',
                'to_address' => 'Dest Ave',
            ]);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $response->json('data.id'),
            'user_id' => $user->id,
        ]);
    }

    public function test_create_with_invalid_payload_returns_unprocessable(): void
    {
        Carbon::setTestNow('2027-03-01 10:00:00');
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $this->postJson('api/subscriptions', ['trip_date' => 'not-a-date'])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_show_returns_owned_subscription(): void
    {
        Carbon::setTestNow('2027-03-01 10:00:00');
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'state' => true,
            'trip_date' => '2027-06-10 12:00:00',
            'from_lat' => -10.0,
            'from_lng' => -20.0,
            'to_lat' => -11.0,
            'to_lng' => -21.0,
        ]);
        $this->actingAs($user, 'api');

        $this->getJson("api/subscriptions/{$subscription->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $subscription->id)
            ->assertJsonPath('data.user_id', $user->id);
    }

    public function test_update_returns_updated_subscription(): void
    {
        Carbon::setTestNow('2027-03-01 10:00:00');
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'state' => true,
            'trip_date' => '2027-06-10 12:00:00',
            'from_lat' => -10.0,
            'from_lng' => -20.0,
            'to_lat' => -11.0,
            'to_lng' => -21.0,
        ]);
        $this->actingAs($user, 'api');

        $payload = $this->validSubscriptionPayload([
            'trip_date' => '2027-08-20 09:00:00',
            'from_address' => 'Updated origin',
        ]);

        $response = $this->putJson("api/subscriptions/{$subscription->id}", $payload);

        $response->assertOk()
            ->assertJsonPath('data.id', $subscription->id)
            ->assertJsonFragment(['from_address' => 'Updated origin']);

        $this->assertSame(
            '2027-08-20 09:00:00',
            $subscription->fresh()->trip_date->format('Y-m-d H:i:s')
        );
    }

    public function test_index_returns_active_subscriptions_for_user(): void
    {
        $user = User::factory()->create();
        $active = Subscription::factory()->create([
            'user_id' => $user->id,
            'state' => true,
        ]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'state' => false,
        ]);
        $this->actingAs($user, 'api');

        $response = $this->getJson('api/subscriptions');

        $response->assertOk();
        $rows = $response->json('data');
        $this->assertIsArray($rows);
        $ids = array_map(static fn ($row) => (int) ($row['id'] ?? 0), $rows);
        $this->assertContains($active->id, $ids);
    }

    public function test_delete_returns_ok_payload_and_removes_subscription(): void
    {
        Carbon::setTestNow('2027-03-01 10:00:00');
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'state' => true,
            'trip_date' => '2027-06-10 12:00:00',
            'from_lat' => -10.0,
            'from_lng' => -20.0,
            'to_lat' => -11.0,
            'to_lng' => -21.0,
        ]);
        $this->actingAs($user, 'api');

        $this->deleteJson("api/subscriptions/{$subscription->id}")
            ->assertOk()
            ->assertExactJson(['data' => 'ok']);

        $this->assertNull(Subscription::query()->find($subscription->id));
    }
}
