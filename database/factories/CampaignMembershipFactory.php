<?php

namespace Database\Factories;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CampaignMembership>
 */
class CampaignMembershipFactory extends Factory
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
            'user_id' => User::factory(),
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => null,
            'assigned_at' => now(),
        ];
    }

    public function gm(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => CampaignMembershipRole::GM->value,
        ]);
    }

    public function trustedPlayer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => CampaignMembershipRole::TRUSTED_PLAYER->value,
        ]);
    }
}
