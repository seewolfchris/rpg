<?php

namespace Database\Factories;

use App\Models\CampaignGmContactMessage;
use App\Models\CampaignGmContactThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CampaignGmContactMessage>
 */
class CampaignGmContactMessageFactory extends Factory
{
    protected $model = CampaignGmContactMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'thread_id' => CampaignGmContactThread::factory(),
            'user_id' => User::factory(),
            'content' => fake()->paragraph(),
        ];
    }
}
