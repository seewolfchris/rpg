<?php

declare(strict_types=1);

namespace App\Actions\WorldCharacterOptions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReorderWorldCharacterOptionAction
{
    public function execute(int $worldId, int $optionId, string $table, string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('Unsupported move direction.');
        }

        $orderedIds = DB::table($table)
            ->where('world_id', $worldId)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        $currentIndex = array_search($optionId, $orderedIds, true);
        if ($currentIndex === false) {
            return;
        }

        $targetIndex = $direction === 'up'
            ? $currentIndex - 1
            : $currentIndex + 1;

        if (! isset($orderedIds[$targetIndex])) {
            return;
        }

        [$orderedIds[$currentIndex], $orderedIds[$targetIndex]] = [$orderedIds[$targetIndex], $orderedIds[$currentIndex]];

        DB::transaction(function () use ($table, $orderedIds): void {
            foreach ($orderedIds as $index => $id) {
                DB::table($table)
                    ->where('id', $id)
                    ->update([
                        'position' => ($index + 1) * 10,
                        'updated_at' => now(),
                    ]);
            }
        });
    }
}
