<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use STS\Models\SupportReplyTemplate;
use STS\Models\User;

/**
 * @extends Factory<SupportReplyTemplate>
 */
class SupportReplyTemplateFactory extends Factory
{
    protected $model = SupportReplyTemplate::class;

    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'name' => fake()->unique()->sentence(3),
            'short_description' => fake()->optional()->sentence(),
            'body_markdown' => fake()->paragraph(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];
    }
}
