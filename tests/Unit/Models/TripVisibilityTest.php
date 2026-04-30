<?php

namespace Tests\Unit\Models;

use STS\Models\Trip;
use STS\Models\TripVisibility;
use STS\Models\User;
use Tests\TestCase;

class TripVisibilityTest extends TestCase
{
    public function test_model_uses_non_incrementing_and_no_timestamps(): void
    {
        $model = new TripVisibility;

        $this->assertFalse($model->getIncrementing());
        $this->assertFalse($model->usesTimestamps());
    }

    public function test_fillable_contains_user_id_and_trip_id(): void
    {
        $this->assertSame([
            'user_id',
            'trip_id',
        ], (new TripVisibility)->getFillable());
    }

    public function test_belongs_to_trip_and_user(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $owner->id]);

        TripVisibility::query()->create([
            'user_id' => $viewer->id,
            'trip_id' => $trip->id,
        ]);

        $row = TripVisibility::query()
            ->where('user_id', $viewer->id)
            ->where('trip_id', $trip->id)
            ->firstOrFail();

        $this->assertTrue($row->trip->is($trip));
        $this->assertTrue($row->user->is($viewer));
    }

    public function test_delete_uses_composite_key_via_set_keys_for_save_query(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $owner->id]);

        TripVisibility::query()->create([
            'user_id' => $viewer->id,
            'trip_id' => $trip->id,
        ]);

        $row = TripVisibility::query()
            ->where('user_id', $viewer->id)
            ->where('trip_id', $trip->id)
            ->firstOrFail();

        $row->delete();

        $this->assertDatabaseMissing('user_visibility_trip', [
            'user_id' => $viewer->id,
            'trip_id' => $trip->id,
        ]);
    }

    public function test_same_user_can_have_rows_for_different_trips(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $tripA = Trip::factory()->create(['user_id' => $owner->id]);
        $tripB = Trip::factory()->create(['user_id' => $owner->id]);

        TripVisibility::query()->create(['user_id' => $viewer->id, 'trip_id' => $tripA->id]);
        TripVisibility::query()->create(['user_id' => $viewer->id, 'trip_id' => $tripB->id]);

        $this->assertSame(2, TripVisibility::query()->where('user_id', $viewer->id)->count());
    }

    public function test_table_name_is_user_visibility_trip(): void
    {
        $this->assertSame('user_visibility_trip', (new TripVisibility)->getTable());
    }
}
