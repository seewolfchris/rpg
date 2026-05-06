<?php

declare(strict_types=1);

namespace App\Domain\Magic;

use App\Domain\Magic\Data\MagicActionResult;

class MagicResultPostRenderer
{
    public function render(MagicActionResult $result): string
    {
        $payload = $result->toArray();

        /** @var array<string, mixed> $spellRoll */
        $spellRoll = (array) ($payload['spell_roll'] ?? []);
        /** @var array<string, mixed> $defense */
        $defense = (array) ($payload['defense'] ?? []);
        /** @var array<string, mixed> $aeCost */
        $aeCost = (array) ($payload['ae_cost'] ?? []);
        /** @var array<string, mixed> $effect */
        $effect = (array) ($payload['effect'] ?? []);

        $spellSuccess = (bool) ($spellRoll['is_success'] ?? false);
        $defenseAttempted = (bool) ($defense['attempted'] ?? false);
        $defenseSuccess = (bool) ($defense['is_success'] ?? false);

        $lines = [
            '[Magieaktion]',
            'Zaubernder: '.(string) data_get($payload, 'actor.name', 'Unbekannt'),
            'Ziel: '.(string) data_get($payload, 'target.name', 'Unbekannt'),
            'Zauber: '.(string) ($payload['spell_name'] ?? 'Unbenannter Zauber'),
        ];

        $intentText = $this->extractPrefixedLine($result->logLines, 'Absicht: ');
        if ($intentText !== null) {
            $lines[] = 'Absicht: '.$intentText;
        }

        $lines[] = '';
        $lines[] = sprintf(
            'Zauberwurf: %d / %d -> %s',
            (int) ($spellRoll['total'] ?? 0),
            (int) ($spellRoll['target_value'] ?? 0),
            $spellSuccess ? 'Erfolg' : 'misslungen',
        );

        if ($defenseAttempted) {
            $defenseLabel = $this->nullableString($defense['label'] ?? null) ?? 'Magieabwehr';

            $lines[] = sprintf(
                '%s: %d / %d -> %s',
                $defenseLabel,
                (int) ($defense['total'] ?? 0),
                (int) ($defense['target_value'] ?? 0),
                $defenseSuccess ? 'Erfolg' : 'misslungen',
            );
        }

        $requestedAeCost = (int) ($aeCost['requested_ae_cost'] ?? 0);
        $resultingActorAeCurrent = is_int($aeCost['resulting_actor_ae_current'] ?? null)
            ? (int) $aeCost['resulting_actor_ae_current']
            : null;
        $resultingActorAeMax = is_int($aeCost['resulting_actor_ae_max'] ?? null)
            ? (int) $aeCost['resulting_actor_ae_max']
            : null;

        if ($resultingActorAeCurrent !== null && $resultingActorAeMax !== null) {
            $lines[] = sprintf('Kosten: %d AE (AE: %d / %d)', $requestedAeCost, $resultingActorAeCurrent, $resultingActorAeMax);
        } else {
            $lines[] = sprintf('Kosten: %d AE', $requestedAeCost);
        }

        if (! $spellSuccess) {
            $lines[] = 'Ergebnis: Der Zauber misslingt. Keine Wirkung.';

            return $this->appendResolutionNote($lines, $result->logLines);
        }

        if ($defenseAttempted && $defenseSuccess) {
            $lines[] = 'Ergebnis: Die Wirkung wird abgewehrt. Kein Effekt.';

            return $this->appendResolutionNote($lines, $result->logLines);
        }

        $effectType = (string) ($effect['effect_type'] ?? MagicService::EFFECT_NARRATIVE);
        $wasApplied = (bool) ($effect['was_applied'] ?? false);

        if (! $wasApplied) {
            $lines[] = 'Ergebnis: Keine Wirkung angewendet.';

            return $this->appendResolutionNote($lines, $result->logLines);
        }

        switch ($effectType) {
            case MagicService::EFFECT_LE_DAMAGE:
                $rawAppliedLeDelta = $effect['applied_le_delta'] ?? null;
                $appliedDamage = is_int($rawAppliedLeDelta)
                    ? max(0, -$rawAppliedLeDelta)
                    : max(0, (int) ($effect['effect_amount'] ?? 0));
                $lines[] = sprintf('Wirkung: %d LE Schaden', $appliedDamage);
                $lines[] = sprintf('Ergebnis: %s verliert %d LE.', $result->targetName, $appliedDamage);
                $this->appendLeState($lines, $effect);
                break;

            case MagicService::EFFECT_LE_HEAL:
                $rawAppliedLeDelta = $effect['applied_le_delta'] ?? null;
                $appliedHeal = is_int($rawAppliedLeDelta)
                    ? max(0, $rawAppliedLeDelta)
                    : max(0, (int) ($effect['effect_amount'] ?? 0));
                $lines[] = sprintf('Wirkung: %d LE Heilung', $appliedHeal);
                $lines[] = sprintf('Ergebnis: %s erhält %d LE.', $result->targetName, $appliedHeal);
                $this->appendLeState($lines, $effect);
                break;

            case MagicService::EFFECT_AE_DAMAGE:
                $rawAppliedAeDelta = $effect['applied_ae_delta'] ?? null;
                $appliedAeDamage = is_int($rawAppliedAeDelta)
                    ? max(0, -$rawAppliedAeDelta)
                    : max(0, (int) ($effect['effect_amount'] ?? 0));
                $lines[] = sprintf('Wirkung: %d AE Verlust', $appliedAeDamage);
                $lines[] = sprintf('Ergebnis: %s verliert %d AE.', $result->targetName, $appliedAeDamage);
                $resultAeCurrent = is_int($effect['resulting_ae_current'] ?? null) ? (int) $effect['resulting_ae_current'] : null;
                $resultAeMax = is_int($effect['resulting_ae_max'] ?? null) ? (int) $effect['resulting_ae_max'] : null;
                if ($resultAeCurrent !== null && $resultAeMax !== null) {
                    $lines[] = sprintf('AE: %d / %d', $resultAeCurrent, $resultAeMax);
                }
                break;

            case MagicService::EFFECT_ATTRIBUTE_DELTA:
                $attributeKey = strtolower((string) ($effect['target_attribute_key'] ?? ''));
                $attributeLabel = $this->attributeLabel($attributeKey);
                $rawAppliedAttributeDelta = $effect['applied_attribute_delta'] ?? null;
                $appliedDelta = is_int($rawAppliedAttributeDelta)
                    ? $rawAppliedAttributeDelta
                    : (int) ($effect['effect_amount'] ?? 0);
                $lines[] = sprintf('Wirkung: %s %+d %%', $attributeLabel, $appliedDelta);

                $attributeCurrent = is_int($effect['resulting_attribute_current'] ?? null) ? (int) $effect['resulting_attribute_current'] : null;
                $attributeMax = is_int($effect['resulting_attribute_max'] ?? null) ? (int) $effect['resulting_attribute_max'] : null;

                if ($attributeCurrent !== null && $attributeMax !== null) {
                    $lines[] = sprintf('Ergebnis: Ziel: %s %d %% / %d %%', $attributeLabel, $attributeMax, $attributeCurrent);
                } else {
                    $lines[] = 'Ergebnis: Attributwirkung angewendet.';
                }
                break;

            case MagicService::EFFECT_NARRATIVE:
            default:
                $lines[] = 'Wirkung: erzählerischer Effekt';
                $lines[] = 'Ergebnis: Der Zauber erzeugt einen sichtbaren, aber nicht numerisch erfassten Effekt.';
                break;
        }

        return $this->appendResolutionNote($lines, $result->logLines);
    }

    /**
     * @param  list<string>  $logLines
     */
    private function extractPrefixedLine(array $logLines, string $prefix): ?string
    {
        foreach ($logLines as $line) {
            if (str_starts_with($line, $prefix)) {
                $raw = trim(substr($line, strlen($prefix)));

                return $raw !== '' ? $raw : null;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $logLines
     * @param  list<string>  $lines
     */
    private function appendResolutionNote(array $lines, array $logLines): string
    {
        $resolutionNote = $this->extractPrefixedLine($logLines, 'SL-Notiz: ');
        if ($resolutionNote !== null) {
            $lines[] = 'SL-Notiz: '.$resolutionNote;
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $effect
     * @param  list<string>  $lines
     */
    private function appendLeState(array &$lines, array $effect): void
    {
        $resultLeCurrent = is_int($effect['resulting_le_current'] ?? null) ? (int) $effect['resulting_le_current'] : null;
        $resultLeMax = is_int($effect['resulting_le_max'] ?? null) ? (int) $effect['resulting_le_max'] : null;

        if ($resultLeCurrent !== null && $resultLeMax !== null) {
            $lines[] = sprintf('LE: %d / %d', $resultLeCurrent, $resultLeMax);
        }
    }

    private function attributeLabel(string $attributeKey): string
    {
        return match ($attributeKey) {
            'mu' => 'Mut',
            'kl' => 'Klugheit',
            'in' => 'Intuition',
            'ch' => 'Charisma',
            'ff' => 'Fingerfertigkeit',
            'ge' => 'Gewandtheit',
            'ko' => 'Konstitution',
            'kk' => 'Körperkraft',
            default => strtoupper($attributeKey),
        };
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
