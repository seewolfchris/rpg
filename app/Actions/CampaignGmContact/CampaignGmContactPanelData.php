<?php

namespace App\Actions\CampaignGmContact;

use App\Models\CampaignGmContactMessage;
use App\Models\CampaignGmContactThread;
use App\Models\Character;
use App\Models\Scene;
use Illuminate\Support\Collection;

final readonly class CampaignGmContactPanelData
{
    /**
     * @param  Collection<int, CampaignGmContactThread>  $threads
     * @param  Collection<int, CampaignGmContactMessage>  $selectedThreadMessages
     * @param  Collection<int, Scene>  $sceneOptions
     * @param  Collection<int, Character>  $characterOptions
     */
    public function __construct(
        public Collection $threads,
        public ?CampaignGmContactThread $selectedThread,
        public Collection $selectedThreadMessages,
        public Collection $sceneOptions,
        public Collection $characterOptions,
        public bool $canCreateThread,
        public bool $isGmSide,
    ) {}
}
