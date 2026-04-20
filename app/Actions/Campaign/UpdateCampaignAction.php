<?php

declare(strict_types=1);

namespace App\Actions\Campaign;

use App\Models\Campaign;
use App\Models\World;
use Illuminate\Database\DatabaseManager;

final class UpdateCampaignAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(World $world, Campaign $campaign, array $data): void
    {
        $this->db->transaction(function () use ($world, $campaign, $data): void {
            $lockedCampaign = $this->lockAndVerifyContext($world, $campaign);

            $this->persistCampaign($lockedCampaign, $data);
        }, 3);

        $campaign->refresh();
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistCampaign(Campaign $campaign, array $data): void
    {
        $campaign->update($data);
    }
}
