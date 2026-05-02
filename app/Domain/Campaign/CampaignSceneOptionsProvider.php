<?php

declare(strict_types=1);

namespace App\Domain\Campaign;

use App\Models\Campaign;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Collection;

class CampaignSceneOptionsProvider
{
    /**
     * @return Collection<int, Scene>
     */
    public function forCampaign(Campaign $campaign): Collection
    {
        /** @var Collection<int, Scene> $scenes */
        $scenes = $campaign->scenes()
            ->orderBy('position')
            ->orderBy('title')
            ->get(['id', 'title', 'position']);

        return $scenes;
    }
}
