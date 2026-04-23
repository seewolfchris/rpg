<?php

declare(strict_types=1);

namespace App\Actions\Campaign;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\DatabaseManager;

final class CreateCampaignAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(World $world, User $owner, array $data): Campaign
    {
        /** @var Campaign $campaign */
        $campaign = $this->db->transaction(function () use ($world, $owner, $data): Campaign {
            $lockedWorld = $this->lockAndVerifyContext($world);

            return $this->persistCampaign($lockedWorld, $owner, $data);
        }, 3);

        return $campaign;
    }

    private function lockAndVerifyContext(World $world): World
    {
        /** @var World $lockedWorld */
        $lockedWorld = World::query()
            ->whereKey((int) $world->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedWorld;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistCampaign(World $world, User $owner, array $data): Campaign
    {
        /** @var Campaign $campaign */
        $campaign = Campaign::query()->create(array_merge($data, [
            'world_id' => (int) $world->id,
            'owner_id' => (int) $owner->id,
        ]));

        CampaignMembership::query()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $owner->id,
            'role' => CampaignMembershipRole::GM->value,
            'assigned_by' => (int) $owner->id,
            'assigned_at' => now(),
        ]);

        return $campaign;
    }
}
