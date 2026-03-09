<?php

namespace Database\Factories;

use App\Models\World;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\World>
 */
class WorldFactory extends Factory
{
    protected $model = World::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(fake()->numberBetween(1, 3), true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'tagline' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'is_active' => true,
            'position' => fake()->numberBetween(10, 99),
        ];
    }

    public function chronikenDerAsche(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Chroniken der Asche',
            'slug' => 'chroniken-der-asche',
            'tagline' => 'Duestere Fantasy in den Aschelanden.',
            'description' => 'Die Standardwelt fuer bestehende Kampagnen und Inhalte.',
            'is_active' => true,
            'position' => 10,
        ]);
    }
}
