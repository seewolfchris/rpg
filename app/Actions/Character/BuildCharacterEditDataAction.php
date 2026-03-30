<?php

declare(strict_types=1);

namespace App\Actions\Character;

use App\Models\World;

class BuildCharacterEditDataAction
{
    public function execute(): CharacterEditData
    {
        $worlds = World::query()->active()->ordered()->get(['id', 'name', 'slug']);

        return new CharacterEditData(
            worlds: $worlds,
        );
    }
}
