<?php

declare(strict_types=1);

namespace App\Actions\Character;

use App\Domain\Character\CharacterProgressionService;
use App\Models\Character;

class BuildCharacterShowDataAction
{
    public function __construct(
        private readonly CharacterProgressionService $progressionService,
    ) {}

    public function execute(Character $character): CharacterShowData
    {
        $inventoryLogs = $character->inventoryLogs()
            ->with('actor:id,name')
            ->limit(25)
            ->get();

        $progressionEvents = $character->progressionEvents()
            ->with(['actorUser:id,name', 'campaign:id,title', 'scene:id,title'])
            ->limit(20)
            ->get();

        $progressionState = $this->progressionService->describe($character);

        return new CharacterShowData(
            character: $character,
            inventoryLogs: $inventoryLogs,
            progressionEvents: $progressionEvents,
            progressionState: $progressionState,
        );
    }
}
