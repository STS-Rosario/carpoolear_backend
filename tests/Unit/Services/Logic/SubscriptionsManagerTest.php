<?php

namespace Tests\Unit\Services\Logic;

use Carbon\Carbon;
use Mockery;
use STS\Models\Subscription;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\SubscriptionsRepository;
use STS\Services\Logic\SubscriptionsManager;
use Tests\TestCase;

class SubscriptionsManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function manager(): SubscriptionsManager
    {
        return new SubscriptionsManager(new SubscriptionsRepository);
    }

    /**
     * @return array<string, mixed>
     */
    private function validCreatePayload(array $overrides = []): array
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

    public function test_validator_rejects_trip_date_not_after_now(): void
    {
        Carbon::setTestNow('2027-08-01 12:00:00');
        $v = $this->manager()->validator($this->validCreatePayload([
            'trip_date' => '2027-07-01 10:00:00',
        ]));
        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('trip_date'));

        Carbon::setTestNow();
    }

    public function test_validator_requires_coordinates_when_address_present(): void
    {
        Carbon::setTestNow('2027-01-01 12:00:00');
        $v = $this->manager()->validator([
            'trip_date' => '2027-06-01 10:00:00',
            'from_address' => 'Somewhere',
            'to_address' => 'Elsewhere',
            'to_lat' => -35.0,
            'to_lng' => -59.0,
        ]);
        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('from_lat'));
        $this->assertTrue($v->errors()->has('from_lng'));

        Carbon::setTestNow();
    }

    public function test_validator_rejects_non_string_addresses(): void
    {
        Carbon::setTestNow('2027-01-01 12:00:00');
        $v = $this->manager()->validator([
            'trip_date' => '2027-06-01 10:00:00',
            'from_address' => ['invalid'],
            'from_lat' => -34.6,
            'from_lng' => -58.4,
            'to_address' => ['invalid'],
            'to_lat' => -34.7,
            'to_lng' => -58.5,
        ]);

        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('from_address'));
        $this->assertTrue($v->errors()->has('to_address'));

        Carbon::setTestNow();
    }

    public function test_create_returns_null_and_sets_errors_when_validation_fails(): void
    {
        Carbon::setTestNow('2027-02-01 12:00:00');
        $user = User::factory()->create();
        $manager = $this->manager();

        $this->assertNull($manager->create($user, ['trip_date' => 'not-a-date']));
        $this->assertTrue($manager->getErrors()->has('trip_date'));

        Carbon::setTestNow();
    }

    public function test_create_persists_subscription_and_returns_model(): void
    {
        Carbon::setTestNow('2027-03-01 10:00:00');
        $user = User::factory()->create();
        $payload = $this->validCreatePayload();

        $model = $this->manager()->create($user, $payload);
        $this->assertInstanceOf(Subscription::class, $model);
        $this->assertNotNull($model->id);
        $this->assertTrue((bool) $model->state);
        $this->assertFalse((bool) $model->is_passenger);
        $this->assertSame($user->id, (int) $model->user_id);

        Carbon::setTestNow();
    }

    public function test_create_sets_is_passenger_from_string_false(): void
    {
        Carbon::setTestNow('2027-03-02 10:00:00');
        $user = User::factory()->create();
        $model = $this->manager()->create($user, $this->validCreatePayload([
            'is_passenger' => 'false',
        ]));
        $this->assertFalse((bool) $model->fresh()->is_passenger);

        Carbon::setTestNow();
    }

    public function test_create_rejects_duplicate_active_subscription(): void
    {
        Carbon::setTestNow('2027-04-01 09:00:00');
        $user = User::factory()->create();
        $payload = $this->validCreatePayload();
        $manager = $this->manager();

        $this->assertNotNull($manager->create($user, $payload));
        $this->assertNull($manager->create($user, $payload));
        $this->assertSame('subscription_exist', $manager->getErrors()['error']);

        Carbon::setTestNow();
    }

    public function test_create_allows_same_corridor_when_passenger_flag_differs(): void
    {
        Carbon::setTestNow('2027-05-01 09:00:00');
        $user = User::factory()->create();
        $base = $this->validCreatePayload();
        $manager = $this->manager();

        $this->assertNotNull($manager->create($user, array_merge($base, ['is_passenger' => false])));
        $second = $manager->create($user, array_merge($base, ['is_passenger' => true]));
        $this->assertNotNull($second);
        $this->assertTrue((bool) $second->is_passenger);

        Carbon::setTestNow();
    }

    public function test_create_rejects_duplicate_when_optional_geometry_and_date_are_both_empty(): void
    {
        Carbon::setTestNow('2027-05-01 09:00:00');
        $user = User::factory()->create();
        $manager = $this->manager();
        $payload = [
            'is_passenger' => false,
        ];

        $this->assertNotNull($manager->create($user, $payload));
        $this->assertNull($manager->create($user, $payload));
        $this->assertSame('subscription_exist', $manager->getErrors()['error']);

        Carbon::setTestNow();
    }

    public function test_show_returns_model_for_owner(): void
    {
        $user = User::factory()->create();
        $sub = Subscription::factory()->create(['user_id' => $user->id, 'state' => true]);

        $found = $this->manager()->show($user, $sub->id);
        $this->assertNotNull($found);
        $this->assertTrue($found->is($sub));
    }

    public function test_show_returns_null_for_other_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $sub = Subscription::factory()->create(['user_id' => $owner->id, 'state' => true]);

        $manager = $this->manager();
        $this->assertNull($manager->show($other, $sub->id));
        $this->assertSame('model_not_found', $manager->getErrors()['error']);
    }

    public function test_update_persists_when_owner_and_valid(): void
    {
        Carbon::setTestNow('2027-06-01 08:00:00');
        $user = User::factory()->create();
        $sub = Subscription::factory()->create([
            'user_id' => $user->id,
            'state' => true,
            'trip_date' => Carbon::parse('2027-09-10 10:00:00'),
            'from_address' => 'Old',
            'from_lat' => -34.0,
            'from_lng' => -58.0,
            'to_address' => 'OldTo',
            'to_lat' => -35.0,
            'to_lng' => -59.0,
        ]);

        $updated = $this->manager()->update($user, $sub->id, $this->validCreatePayload([
            'trip_date' => '2027-10-20 12:00:00',
            'from_address' => 'NewFrom',
        ]));

        $this->assertNotNull($updated);
        $this->assertSame('NewFrom', $updated->fresh()->from_address);

        Carbon::setTestNow();
    }

    public function test_update_returns_null_when_subscription_missing(): void
    {
        Carbon::setTestNow('2027-07-01 10:00:00');
        $user = User::factory()->create();
        $manager = $this->manager();

        $this->assertNull($manager->update($user, 999999999, $this->validCreatePayload()));
        $this->assertSame('subscript_not_found', $manager->getErrors()['error']);

        Carbon::setTestNow();
    }

    public function test_update_returns_null_and_sets_validation_errors_when_payload_is_invalid(): void
    {
        Carbon::setTestNow('2027-07-01 10:00:00');
        $user = User::factory()->create();
        $sub = Subscription::factory()->create(['user_id' => $user->id, 'state' => true]);
        $manager = $this->manager();

        $this->assertNull($manager->update($user, $sub->id, [
            'trip_date' => 'not-a-date',
            'from_address' => 'x',
        ]));
        $this->assertTrue($manager->getErrors()->has('trip_date'));
        $this->assertTrue($manager->getErrors()->has('from_lat'));
        $this->assertTrue($manager->getErrors()->has('from_lng'));

        Carbon::setTestNow();
    }

    public function test_show_accepts_equivalent_scalar_ids_for_owner_check(): void
    {
        $persisted = User::factory()->create();
        $viewer = new User;
        $viewer->id = (string) $persisted->id;
        $sub = Subscription::factory()->create(['user_id' => $persisted->id, 'state' => true]);

        $found = $this->manager()->show($viewer, $sub->id);
        $this->assertNotNull($found);
        $this->assertSame($sub->id, $found->id);
    }

    public function test_delete_removes_subscription_for_owner(): void
    {
        $user = User::factory()->create();
        $sub = Subscription::factory()->create(['user_id' => $user->id, 'state' => true]);

        $this->assertTrue($this->manager()->delete($user, $sub->id));
        $this->assertNull(Subscription::query()->find($sub->id));
    }

    public function test_delete_returns_null_with_model_not_found_error_when_not_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $sub = Subscription::factory()->create(['user_id' => $owner->id, 'state' => true]);
        $manager = $this->manager();

        $this->assertNull($manager->delete($other, $sub->id));
        $this->assertSame('model_not_found', $manager->getErrors()['error']);
    }

    public function test_delete_returns_null_with_cant_delete_model_when_repository_delete_fails(): void
    {
        $user = User::factory()->create();
        $sub = Subscription::factory()->create(['user_id' => $user->id, 'state' => true]);

        $repo = Mockery::mock(SubscriptionsRepository::class);
        $repo->shouldReceive('show')->once()->with($sub->id)->andReturn($sub);
        $repo->shouldReceive('delete')->once()->with(Mockery::on(fn ($m) => $m->id === $sub->id))->andReturn(false);

        $manager = new SubscriptionsManager($repo);
        $this->assertNull($manager->delete($user, $sub->id));
        $this->assertSame('cant_delete_model', $manager->getErrors()['error']);
    }

    public function test_index_returns_only_active_subscriptions(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id, 'state' => true]);
        Subscription::factory()->create(['user_id' => $user->id, 'state' => false]);

        $list = $this->manager()->index($user->fresh());
        $this->assertCount(1, $list);
        $this->assertTrue((bool) $list->first()->state);
    }

    public function test_sync_trip_runs_without_error(): void
    {
        $this->expectNotToPerformAssertions();
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $this->manager()->syncTrip($trip->load('user'));
    }
}
