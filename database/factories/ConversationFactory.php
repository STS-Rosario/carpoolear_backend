<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use STS\Models\Conversation;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\STS\Models\Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = \STS\Models\Conversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => null,
            'title'   => fake()->safeEmail(),
            'type'    => Conversation::TYPE_PRIVATE_CONVERSATION,
        ];
    }
}
