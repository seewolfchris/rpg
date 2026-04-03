<?php

declare(strict_types=1);

namespace App\Actions\World;

use App\Models\World;
use Illuminate\Database\DatabaseManager;

class ReorderWorldsAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array<int, int>  $orderedIds
     */
    public function execute(array $orderedIds): void
    {
        $this->db->transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $index => $worldId) {
                World::query()
                    ->whereKey($worldId)
                    ->update(['position' => ($index + 1) * 10]);
            }
        });
    }
}
