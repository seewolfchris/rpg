<?php

namespace App\Support;

use App\Models\DiceRoll;

class ProbeRoller
{
    /**
     * @return array{mode: string, rolls: array<int, int>, kept_roll: int, modifier: int, total: int, critical_success: bool, critical_failure: bool}
     */
    public function roll(string $mode, int $modifier = 0): array
    {
        $normalizedMode = in_array($mode, DiceRoll::ALLOWED_MODES, true)
            ? $mode
            : DiceRoll::MODE_NORMAL;

        $rolls = [$this->rollValue()];
        if (in_array($normalizedMode, [DiceRoll::MODE_ADVANTAGE, DiceRoll::MODE_DISADVANTAGE], true)) {
            $rolls[] = $this->rollValue();
        }

        $keptRoll = $this->resolveKeptRoll($normalizedMode, $rolls);
        $total = $keptRoll + $modifier;

        return [
            'mode' => $normalizedMode,
            'rolls' => $rolls,
            'kept_roll' => $keptRoll,
            'modifier' => $modifier,
            'total' => $total,
            'critical_success' => $keptRoll === 100,
            'critical_failure' => $keptRoll === 1,
        ];
    }

    /**
     * @param  array<int, int>  $rolls
     */
    private function resolveKeptRoll(string $mode, array $rolls): int
    {
        return match ($mode) {
            DiceRoll::MODE_ADVANTAGE => max($rolls),
            DiceRoll::MODE_DISADVANTAGE => min($rolls),
            default => $rolls[0],
        };
    }

    private function rollValue(): int
    {
        return random_int(1, 100);
    }
}
