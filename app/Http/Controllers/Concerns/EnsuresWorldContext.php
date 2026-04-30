<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Campaign;
use App\Models\EncyclopediaCategory;
use App\Models\Handout;
use App\Models\Post;
use App\Models\Scene;
use App\Models\StoryLogEntry;
use App\Models\World;

trait EnsuresWorldContext
{
    protected function ensureCampaignBelongsToWorld(World $world, Campaign $campaign): void
    {
        abort_unless((int) $campaign->world_id === (int) $world->id, 404);
    }

    protected function ensureSceneBelongsToCampaign(Campaign $campaign, Scene $scene): void
    {
        abort_unless((int) $scene->campaign_id === (int) $campaign->id, 404);
    }

    protected function ensureSceneBelongsToWorld(World $world, Campaign $campaign, Scene $scene): void
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensureSceneBelongsToCampaign($campaign, $scene);
    }

    protected function ensurePostBelongsToWorld(World $world, Post $post): void
    {
        $campaignWorldId = (int) ($post->scene->campaign->world_id ?? 0);

        abort_unless($campaignWorldId === (int) $world->id, 404);
    }

    protected function ensureHandoutBelongsToCampaign(Campaign $campaign, Handout $handout): void
    {
        abort_unless((int) $handout->campaign_id === (int) $campaign->id, 404);
    }

    protected function ensureStoryLogEntryBelongsToCampaign(Campaign $campaign, StoryLogEntry $storyLogEntry): void
    {
        abort_unless((int) $storyLogEntry->campaign_id === (int) $campaign->id, 404);
    }

    protected function ensureCategoryBelongsToWorld(World $world, EncyclopediaCategory $category): void
    {
        abort_unless((int) $category->world_id === (int) $world->id, 404);
    }
}
