<?php

namespace Database\Factories;

use App\Models\Scene;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scene_id' => Scene::factory(),
            'user_id' => User::factory(),
            'character_id' => null,
            'post_type' => fake()->randomElement(['ic', 'ooc']),
            'content_format' => fake()->randomElement(['markdown', 'bbcode', 'plain']),
            'content' => fake()->paragraphs(2, true),
            'meta' => null,
            'moderation_status' => 'pending',
            'approved_at' => null,
            'approved_by' => null,
            'is_edited' => false,
            'edited_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => User::factory()->gm(),
        ]);
    }
}
