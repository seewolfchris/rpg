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
            'previous_scene_id' => null,
            'summary' => fake()->optional()->sentence(12),
            'description' => fake()->optional()->paragraphs(2, true),
            'header_image_path' => null,
            'status' => fake()->randomElement(['open', 'closed', 'archived']),
            'mood' => fake()->randomElement(['neutral', 'dark', 'cheerful', 'mystic', 'tense']),
            'position' => fake()->numberBetween(0, 25),
            'allow_ooc' => true,
            'opens_at' => fake()->optional()->dateTimeBetween('-1 month', '+1 month'),
            'closes_at' => null,
        ];
    }
}
