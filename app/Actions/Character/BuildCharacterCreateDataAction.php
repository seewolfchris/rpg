<?php

declare(strict_types=1);

namespace App\Actions\Character;

use App\Models\World;

class BuildCharacterCreateDataAction
{
    public function execute(string $selectedWorldSlug): CharacterCreateData
    {
        $worlds = World::query()->active()->ordered()->get(['id', 'name', 'slug']);
        $normalizedWorldSlug = trim($selectedWorldSlug);
        $lookupSlug = $normalizedWorldSlug !== '' ? $normalizedWorldSlug : World::defaultSlug();
        $selectedWorld = $worlds->firstWhere('slug', $lookupSlug) ?? $worlds->first();

        return new CharacterCreateData(
            worlds: $worlds,
            selectedWorld: $selectedWorld,
        );
    }
}
