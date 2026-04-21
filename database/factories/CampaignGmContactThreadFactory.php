<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignGmContactThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CampaignGmContactThread>
 */
class CampaignGmContactThreadFactory extends Factory
{
    protected $model = CampaignGmContactThread::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'created_by' => User::factory(),
            'subject' => fake()->sentence(4),
            'status' => CampaignGmContactThread::STATUS_WAITING_FOR_GM,
            'character_id' => null,
            'scene_id' => null,
            'last_activity_at' => now(),
        ];
    }
}
