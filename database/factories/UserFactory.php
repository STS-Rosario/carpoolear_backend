<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('123456'),
            'remember_token' => Str::random(10),
            'last_connection' => \Carbon\Carbon::now(),
            'active'         => true,
            'emails_notifications' => true,
            'terms_and_conditions' => true,
            'do_not_alert_request_seat' => true,
            'do_not_alert_accept_passenger' => true,
            'do_not_alert_pending_rates' => true,
            'description' => fake()->sentence(),
            'image' => 'default.png',
            'autoaccept_requests' => false,
            'on_boarding_view' => false,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
