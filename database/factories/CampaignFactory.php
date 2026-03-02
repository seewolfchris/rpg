<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->gm(),
            'title' => fake()->unique()->sentence(3),
            'slug' => fake()->unique()->slug(),
            'summary' => fake()->optional()->paragraph(),
            'lore' => fake()->optional()->paragraphs(2, true),
            'is_public' => fake()->boolean(60),
            'status' => fake()->randomElement(['draft', 'active', 'archived']),
            'starts_at' => fake()->optional()->dateTimeBetween('-2 months', '+1 month'),
            'ends_at' => null,
        ];
    }
}
