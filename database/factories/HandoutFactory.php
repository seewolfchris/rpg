<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Handout>
 */
class HandoutFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'scene_id' => null,
            'created_by' => User::factory(),
            'updated_by' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'revealed_at' => null,
            'version_label' => fake()->optional()->bothify('v#.#'),
            'sort_order' => fake()->optional()->numberBetween(0, 1000),
        ];
    }

    public function revealed(): static
    {
        return $this->state(fn (array $attributes) => [
            'revealed_at' => now(),
        ]);
    }

    public function forScene(Scene $scene): static
    {
        return $this->state(fn (array $attributes) => [
            'campaign_id' => $scene->campaign_id,
            'scene_id' => $scene->id,
        ]);
    }
}
