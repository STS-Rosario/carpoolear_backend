<?php

namespace Tests\Unit\Models;

use STS\Models\Trip;
use STS\Models\TripDay;
use STS\Models\User;
use Tests\TestCase;

class TripDayTest extends TestCase
{
    public function test_belongs_to_trip(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $row = TripDay::query()->create([
            'trip_id' => $trip->id,
            'day' => 'Monday',
            'hour' => '08:00',
        ]);

        $this->assertTrue($row->fresh()->trip->is($trip));
    }

    public function test_persists_day_and_hour(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $row = TripDay::query()->create([
            'trip_id' => $trip->id,
            'day' => 'Wednesday',
            'hour' => '17:45',
        ]);

        $row = $row->fresh();
        $this->assertSame('Wednesday', $row->day);
        $this->assertSame('17:45', $row->hour);
    }

    public function test_trip_can_have_multiple_day_rows(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        TripDay::query()->create(['trip_id' => $trip->id, 'day' => 'Monday', 'hour' => '09:00']);
        TripDay::query()->create(['trip_id' => $trip->id, 'day' => 'Friday', 'hour' => '18:30']);

        $this->assertSame(2, TripDay::query()->where('trip_id', $trip->id)->count());
    }

    public function test_table_name_is_recurrent_trip_day(): void
    {
        $this->assertSame('recurrent_trip_day', (new TripDay)->getTable());
    }
}
