<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Scene;
use App\Models\StoryLogEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoryLogEntry>
 */
class StoryLogEntryFactory extends Factory
{
    /**
     * @var class-string<StoryLogEntry>
     */
    protected $model = StoryLogEntry::class;

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
            'title' => fake()->sentence(5),
            'body' => fake()->optional()->paragraphs(2, true),
            'revealed_at' => null,
            'sort_order' => fake()->optional()->numberBetween(0, 1000),
        ];
    }

    public function revealed(): static
    {
        return $this->state(fn (): array => [
            'revealed_at' => now(),
        ]);
    }

    public function forScene(Scene $scene): static
    {
        return $this->state(fn (): array => [
            'campaign_id' => (int) $scene->campaign_id,
            'scene_id' => (int) $scene->id,
        ]);
    }
}
