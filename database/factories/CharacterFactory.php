<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Character>
 */
class CharacterFactory extends Factory
{
    protected $model = Character::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->firstName().' von '.fake()->city(),
            'epithet' => fake()->optional()->sentence(3),
            'bio' => fake()->paragraphs(3, true),
            'avatar_path' => null,
            'strength' => fake()->numberBetween(6, 18),
            'dexterity' => fake()->numberBetween(6, 18),
            'constitution' => fake()->numberBetween(6, 18),
            'intelligence' => fake()->numberBetween(6, 18),
            'wisdom' => fake()->numberBetween(6, 18),
            'charisma' => fake()->numberBetween(6, 18),
        ];
    }
}
