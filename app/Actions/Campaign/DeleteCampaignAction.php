<?php

declare(strict_types=1);

namespace App\Actions\Campaign;

use App\Models\Campaign;
use App\Models\World;
use Illuminate\Database\DatabaseManager;

final class DeleteCampaignAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(World $world, Campaign $campaign): void
    {
        $this->db->transaction(function () use ($world, $campaign): void {
            $lockedCampaign = $this->lockAndVerifyContext($world, $campaign);

            $this->persistDeletion($lockedCampaign);
        }, 3);
    }

    private function lockAndVerifyContext(World $world, Campaign $campaign): Campaign
    {
        /** @var World $lockedWorld */
        $lockedWorld = World::query()
            ->whereKey((int) $world->id)
            ->lockForUpdate()
            ->firstOrFail();

        /** @var Campaign $lockedCampaign */
        $lockedCampaign = Campaign::query()
            ->whereKey((int) $campaign->id)
            ->where('world_id', (int) $lockedWorld->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedCampaign;
    }

    private function persistDeletion(Campaign $campaign): void
    {
        $campaign->delete();
    }
}
