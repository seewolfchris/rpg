<?php

declare(strict_types=1);

namespace App\Actions\Character;

use App\Models\World;
use Illuminate\Database\Eloquent\Collection;

final readonly class CharacterCreateData
{
    /**
     * @param  Collection<int, World>  $worlds
     */
    public function __construct(
        public Collection $worlds,
        public ?World $selectedWorld,
    ) {}
}
