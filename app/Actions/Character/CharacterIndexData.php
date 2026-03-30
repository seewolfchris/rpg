<?php

declare(strict_types=1);

namespace App\Actions\Character;

use App\Models\Character;
use App\Models\World;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final readonly class CharacterIndexData
{
    /**
     * @param  LengthAwarePaginator<int, Character>  $characters
     * @param  Collection<int, World>  $worlds
     * @param  array<string, mixed>  $characterStatuses
     */
    public function __construct(
        public LengthAwarePaginator $characters,
        public Collection $worlds,
        public ?World $selectedWorld,
        public string $selectedStatus,
        public array $characterStatuses,
    ) {}
}
