<?php

namespace Tests\Unit;

use Carbon\Carbon;
use STS\Models\Subscription;
use STS\Models\Trip;
use STS\Models\TripPoint;
use STS\Models\User;
use STS\Repository\SubscriptionsRepository;
use STS\Services\Logic\SubscriptionsManager;
use Tests\TestCase;

class SubscriptionsTest extends TestCase
{
    private SubscriptionsManager $subscriptionsManager;

    private SubscriptionsRepository $subscriptionsRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriptionsManager = $this->app->make(SubscriptionsManager::class);
        $this->subscriptionsRepository = $this->app->make(SubscriptionsRepository::class);
        Carbon::setTestNow('2028-01-01 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'trip_date' => Carbon::now()->addHours(2)->toDateTimeString(),
            'from_address' => 'Rosario, Santa Fe, Argentina',
            'from_lat' => -32.9465,
            'from_lng' => -60.6698,
            'to_address' => 'Mendoza, Mendoza, Argentina',
            'to_lat' => -32.897273,
            'to_lng' => -68.834067,
            'is_passenger' => false,
        ], $overrides);
    }

    public function test_create_subscription_sets_owner_and_persists_model(): void
    {
        $user = User::factory()->create();
        $model = $this->subscriptionsManager->create($user, $this->validPayload());

        $this->assertNotNull($model);
        $this->assertSame($user->id, (int) $model->user_id);
        $this->assertDatabaseHas('subscriptions', ['id' => $model->id, 'user_id' => $user->id]);
    }

    public function test_update_subscription_changes_trip_date(): void
    {
        $user = User::factory()->create();
        $model = Subscription::factory()->create([
            'user_id' => $user->id,
            'trip_date' => Carbon::now()->addDays(2),
        ]);
        $newTripDate = Carbon::now()->addDays(5)->toDateTimeString();
        $updatedModel = $this->subscriptionsManager->update($user, $model->id, $this->validPayload([
            'trip_date' => $newTripDate,
        ]));

        $this->assertNotNull($updatedModel);
        $this->assertSame($newTripDate, $updatedModel->fresh()->trip_date->toDateTimeString());
    }

    public function test_show_subscription_returns_model_for_owner(): void
    {
        $user = User::factory()->create();
        $model = Subscription::factory()->create(['user_id' => $user->id]);

        $showedModel = $this->subscriptionsManager->show($user, $model->id);
        $this->assertNotNull($showedModel);
        $this->assertTrue($showedModel->is($model));
    }

    public function test_delete_subscription_removes_row(): void
    {
        $user = User::factory()->create();
        $model = Subscription::factory()->create(['user_id' => $user->id]);

        $result = $this->subscriptionsManager->delete($user, $model->id);
        $this->assertTrue($result);
        $this->assertNull(Subscription::query()->find($model->id));
    }

    public function test_index_subscription_returns_users_active_rows(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id, 'state' => true]);
        Subscription::factory()->create(['user_id' => $user->id, 'state' => true]);
        Subscription::factory()->create(['user_id' => $user->id, 'state' => false]);

        $result = $this->subscriptionsManager->index($user);
        $this->assertCount(2, $result);
        $this->assertTrue((bool) $result->first()->state);
    }

    public function test_matcher_finds_subscription_when_trip_points_fit_corridor(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Subscription::factory()->create(['user_id' => $user1->id, 'trip_date' => null]);
        $trip = Trip::factory()->create(['user_id' => $user2->id]);

        $trip->points()->save(TripPoint::factory()->rosario()->make());
        $trip->points()->save(TripPoint::factory()->mendoza()->make());

        $ss = $this->subscriptionsRepository->search($user2, $trip);
        $this->assertCount(1, $ss);
        $this->assertSame($user1->id, (int) $ss->first()->user_id);
    }

    public function test_matcher_with_specific_destination_radius_matches_trip(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $user1->id,
            'trip_date' => null,
            'to_address' => 'Mendoza, Mendoza, Argentina',
            'to_json_address' => ['ciudad' => 'Mendoza', 'provincia' => 'Mendoza'],
            'to_lat' => -32.897273,
            'to_lng' => -68.834067,
            'to_sin_lat' => sin(deg2rad(-32.897273)),
            'to_sin_lng' => sin(deg2rad(-68.834067)),
            'to_cos_lat' => cos(deg2rad(-32.897273)),
            'to_cos_lng' => cos(deg2rad(-68.834067)),
            'to_radio' => 10000,
        ]);
        $trip = Trip::factory()->create([
            'friendship_type_id' => 2,
            'user_id' => $user2->id,
        ]);

        $trip->points()->save(TripPoint::factory()->rosario()->make());
        $trip->points()->save(TripPoint::factory()->mendoza()->make());

        $ss = $this->subscriptionsRepository->search($user2, $trip);
        $this->assertCount(1, $ss);
        $this->assertSame($user1->id, (int) $ss->first()->user_id);
    }

    public function test_matcher_with_non_matching_origin_returns_empty(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $user1->id,
            'trip_date' => null,
            'to_address' => 'Mendoza, Mendoza, Argentina',
            'to_json_address' => ['ciudad' => 'Mendoza', 'provincia' => 'Mendoza'],
            'to_lat' => -32.897273,
            'to_lng' => -68.834067,
            'to_sin_lat' => sin(deg2rad(-32.897273)),
            'to_sin_lng' => sin(deg2rad(-68.834067)),
            'to_cos_lat' => cos(deg2rad(-32.897273)),
            'to_cos_lng' => cos(deg2rad(-68.834067)),
            'to_radio' => 10000,

            'from_address' => 'Cordoba, Cordoba, Argentina',
            'from_json_address' => ['ciudad' => 'Cordoba', 'provincia' => 'Cordoba'],
            'from_lat' => -31.421045,
            'from_lng' => -64.190543,
            'from_sin_lat' => sin(deg2rad(-31.421045)),
            'from_sin_lng' => sin(deg2rad(-64.190543)),
            'from_cos_lat' => cos(deg2rad(-31.421045)),
            'from_cos_lng' => cos(deg2rad(-64.190543)),
            'from_radio' => 1000,
        ]);
        $trip = Trip::factory()->create([
            'user_id' => $user2->id,
        ]);

        $trip->points()->save(TripPoint::factory()->rosario()->make());
        $trip->points()->save(TripPoint::factory()->mendoza()->make());

        $ss = $this->subscriptionsRepository->search($user2, $trip);
        $this->assertCount(0, $ss);
    }
}
