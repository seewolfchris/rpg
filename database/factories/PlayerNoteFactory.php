<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Character;
use App\Models\PlayerNote;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerNote>
 */
class PlayerNoteFactory extends Factory
{
    /**
     * @var class-string<PlayerNote>
     */
    protected $model = PlayerNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'campaign_id' => Campaign::factory(),
            'scene_id' => null,
            'character_id' => null,
            'title' => fake()->sentence(5),
            'body' => fake()->optional()->paragraphs(2, true),
            'sort_order' => fake()->optional()->numberBetween(0, 1000),
        ];
    }

    public function forScene(Scene $scene): static
    {
        return $this->state(fn (): array => [
            'campaign_id' => (int) $scene->campaign_id,
            'scene_id' => (int) $scene->id,
        ]);
    }

    public function forCharacter(Character $character): static
    {
        return $this->state(fn (): array => [
            'user_id' => (int) $character->user_id,
            'character_id' => (int) $character->id,
        ]);
    }
}
