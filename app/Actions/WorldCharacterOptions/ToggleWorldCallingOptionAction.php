<?php

declare(strict_types=1);

namespace App\Actions\WorldCharacterOptions;

use App\Models\World;
use App\Models\WorldCalling;
use Illuminate\Database\DatabaseManager;

final class ToggleWorldCallingOptionAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(World $world, WorldCalling $callingOption): bool
    {
        $nextActive = false;

        $this->db->transaction(function () use ($world, $callingOption, &$nextActive): void {
            $lockedCallingOption = $this->lockAndVerifyContext($world, $callingOption);

            $nextActive = ! (bool) $lockedCallingOption->is_active;
            $this->persistCalling($lockedCallingOption, $nextActive);
        }, 3);

        $callingOption->refresh();

        return $nextActive;
    }

    private function lockAndVerifyContext(World $world, WorldCalling $callingOption): WorldCalling
    {
        /** @var World $lockedWorld */
        $lockedWorld = World::query()
            ->whereKey((int) $world->id)
            ->lockForUpdate()
            ->firstOrFail();

        /** @var WorldCalling $lockedCallingOption */
        $lockedCallingOption = WorldCalling::query()
            ->whereKey((int) $callingOption->id)
            ->where('world_id', (int) $lockedWorld->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedCallingOption;
    }

    private function persistCalling(WorldCalling $callingOption, bool $nextActive): void
    {
        $callingOption->forceFill([
            'is_active' => $nextActive,
        ]);
        $callingOption->save();
    }
}
