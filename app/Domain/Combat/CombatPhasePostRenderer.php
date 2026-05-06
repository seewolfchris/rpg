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
            $attackTotal = (int) data_get($result, 'attack.total', 0);
            $attackTarget = (int) data_get($result, 'attack.target_value', 0);
            $attackSuccess = (bool) data_get($result, 'attack.is_success', false);
            $defenseAttempted = (bool) data_get($result, 'defense.attempted', false);
            $defenseSuccess = (bool) data_get($result, 'defense.is_success', false);
            $outcome = (array) data_get($result, 'outcome', []);

            $headline = sprintf(
                '%d) %s -> %s%s',
                (int) $item['position'],
                $actorName,
                $targetName,
                $weaponName !== '' ? ' ('.$weaponName.')' : ''
            );
            $lines[] = $headline;

            $lines[] = sprintf(
                '   Angriff: %d / %d -> %s',
                $attackTotal,
                $attackTarget,
                $attackSuccess ? 'Erfolg' : 'misslungen'
            );

            if ($defenseAttempted) {
                $defenseLabel = trim((string) data_get($result, 'defense.label', ''));
                $defenseTotal = (int) data_get($result, 'defense.total', 0);
                $defenseTarget = (int) data_get($result, 'defense.target_value', 0);
                $lines[] = sprintf(
                    '   %s: %d / %d -> %s',
                    $defenseLabel !== '' ? $defenseLabel : 'Verteidigung',
                    $defenseTotal,
                    $defenseTarget,
                    $defenseSuccess ? 'Erfolg' : 'misslungen'
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
}
