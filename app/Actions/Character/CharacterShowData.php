<?php

declare(strict_types=1);

namespace App\Actions\Character;

use App\Models\Character;
use App\Models\CharacterInventoryLog;
use App\Models\CharacterProgressionEvent;
use Illuminate\Database\Eloquent\Collection;

final readonly class CharacterShowData
{
    /**
     * @param  Collection<int, CharacterInventoryLog>  $inventoryLogs
     * @param  Collection<int, CharacterProgressionEvent>  $progressionEvents
     * @param  array{
     *     level: int,
     *     xp_total: int,
     *     xp_current_level_start: int,
     *     xp_next_level_threshold: int,
     *     xp_to_next_level: int,
     *     progress_percent: float,
     *     attribute_points_unspent: int
     * }  $progressionState
     */
    public function __construct(
        public Character $character,
        public Collection $inventoryLogs,
        public Collection $progressionEvents,
        public array $progressionState,
    ) {}
}
