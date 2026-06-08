<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use STS\Models\Trip;
use STS\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\STS\Models\TripLiveShare>
 */
class TripLiveShareFactory extends Factory
{
    protected $model = \STS\Models\TripLiveShare::class;

    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'user_id' => User::factory(),
            'share_token' => Str::random(48),
            'is_active' => true,
            'lat' => null,
            'lng' => null,
            'recorded_at' => null,
            'stop_reminder_sent_at' => null,
            'auto_stopped_at' => null,
            'started_at' => now(),
            'stopped_at' => null,
        ];
    }
}
