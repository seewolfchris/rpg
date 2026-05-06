<?php

declare(strict_types=1);

namespace App\Domain\Combat;

use App\Domain\Combat\Data\CombatPhaseResolutionResult;

class CombatPhasePostRenderer
{
    public function render(CombatPhaseResolutionResult $resolution): string
    {
        $payload = $resolution->toArray();

        $lines = [
            '[Kampfphase '.$resolution->phaseNumber.']',
            (string) $payload['summary'],
            '',
        ];

        /** @var list<array{action_id:int,position:int,result:array<string,mixed>}> $results */
        $results = $payload['results'];

        foreach ($results as $item) {
            $result = $item['result'];
            $actorName = (string) data_get($result, 'actor.name', 'Unbekannt');
            $targetName = (string) data_get($result, 'target.name', 'Unbekannt');
            $weaponName = trim((string) data_get($result, 'weapon_name', ''));
            $defenseAttempted = (bool) data_get($result, 'defense.attempted', false);
            $outcome = (array) data_get($result, 'outcome', []);
            /** @var array<string, mixed> $attack */
            $attack = (array) data_get($result, 'attack', []);
            /** @var array<string, mixed> $defense */
            $defense = (array) data_get($result, 'defense', []);

            $headline = sprintf(
                '%d) %s -> %s%s',
                (int) $item['position'],
                $actorName,
                $targetName,
                $weaponName !== '' ? ' ('.$weaponName.')' : ''
            );
            $lines[] = $headline;

            $lines[] = '   '.$this->formatRollLine('Angriff', $attack);

            if ($defenseAttempted) {
                $defenseLabel = trim((string) data_get($defense, 'label', ''));
                $lines[] = '   '.$this->formatRollLine(
                    $defenseLabel !== '' ? $defenseLabel : 'Verteidigung',
                    $defense,
                );
            }

            if (! (bool) ($outcome['attack_hit'] ?? false)) {
                $lines[] = '   Ergebnis: Kein Treffer.';
                continue;
            }

            if ((bool) ($outcome['defense_prevented_hit'] ?? false)) {
                $lines[] = '   Ergebnis: Der Treffer wird abgewehrt. Kein Schaden.';
                continue;
            }

            $rawDamage = (int) ($outcome['raw_damage'] ?? 0);
            $armor = (int) ($outcome['armor_protection'] ?? 0);
            $effectiveDamage = (int) ($outcome['effective_damage'] ?? 0);

            $lines[] = sprintf('   Schaden: %d - RS %d = %d', $rawDamage, $armor, $effectiveDamage);
            if ($effectiveDamage > 0) {
                $lines[] = sprintf('   Ergebnis: %s verliert %d LE.', $targetName, $effectiveDamage);
            } else {
                $lines[] = '   Ergebnis: Kein wirksamer Schaden.';
            }

            $leCurrent = $outcome['resulting_le_current'] ?? null;
            $leMax = $outcome['resulting_le_max'] ?? null;
            if (is_int($leCurrent) && is_int($leMax)) {
                $lines[] = sprintf('   LE: %d / %d', $leCurrent, $leMax);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $rollData
     */
    private function formatRollLine(string $label, array $rollData): string
    {
        $rolls = $this->normalizeRolls($rollData['rolls'] ?? []);
        $modifier = (int) ($rollData['modifier'] ?? 0);
        $total = (int) ($rollData['total'] ?? 0);
        $target = (int) ($rollData['target_value'] ?? 0);
        $isSuccess = (bool) ($rollData['is_success'] ?? false);
        $keptRoll = is_int($rollData['kept_roll'] ?? null)
            ? (int) $rollData['kept_roll']
            : ($rolls !== [] ? $rolls[0] : $total - $modifier);
        $outcome = $isSuccess ? 'Erfolg' : 'misslungen';

        if (count($rolls) > 1) {
            return sprintf(
                '%s: Würfe %s → behalten %d + Mod %d = %d / Ziel %d → %s',
                $label,
                implode(', ', $rolls),
                $keptRoll,
                $modifier,
                $total,
                $target,
                $outcome,
            );
        }

        return sprintf(
            '%s: Wurf %d + Mod %d = %d / Ziel %d → %s',
            $label,
            $keptRoll,
            $modifier,
            $total,
            $target,
            $outcome,
        );
    }

    /**
     * @return list<int>
     */
    private function normalizeRolls(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rolls = [];

        foreach ($value as $roll) {
            if (is_int($roll)) {
                $rolls[] = $roll;
            }
        }

        return $rolls;
    }
}
