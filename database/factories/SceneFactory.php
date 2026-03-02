<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Scene>
 */
class SceneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'created_by' => User::factory()->gm(),
            'title' => fake()->sentence(4),
            'slug' => fake()->unique()->slug(),
            'summary' => fake()->optional()->sentence(12),
            'description' => fake()->optional()->paragraphs(2, true),
            'status' => fake()->randomElement(['open', 'closed', 'archived']),
            'position' => fake()->numberBetween(0, 25),
            'allow_ooc' => true,
            'opens_at' => fake()->optional()->dateTimeBetween('-1 month', '+1 month'),
            'closes_at' => null,
        ];
    }
}
