<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Combat\CombatService;
use App\Domain\Combat\Data\CombatActionInput;
use App\Domain\Combat\Data\CombatActionResult;
use App\Domain\Combat\Data\CombatActor;
use App\Domain\Combat\Data\CombatTarget;
use App\Domain\Combat\Exceptions\CombatInvariantViolationException;
use App\Domain\Post\StorePostService;
use App\Domain\SceneConflict\SceneConflictActorInputMapper;
use App\Domain\SceneConflict\SceneConflictActorResultApplier;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\Scene\StoreSceneCombatActionRequest;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneConflictActor;
use App\Models\User;
use App\Models\World;
use App\Support\SensitiveFeatureGate;
use Illuminate\Http\RedirectResponse;

class SceneCombatActionController extends Controller
{
    use EnsuresWorldContext;

    public function __construct(
        private readonly CombatService $combatService,
        private readonly StorePostService $storePostService,
        private readonly SceneConflictActorInputMapper $sceneConflictActorInputMapper,
        private readonly SceneConflictActorResultApplier $sceneConflictActorResultApplier,
    ) {}

    public function store(
        StoreSceneCombatActionRequest $request,
        World $world,
        Campaign $campaign,
        Scene $scene,
    ): RedirectResponse {
        abort_unless(SensitiveFeatureGate::enabled('features.combat_tools_enabled', false), 404);
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('view', $scene);

        $actor = $this->authenticatedUser($request);
        abort_unless($campaign->canModeratePosts($actor), 403);

        $data = $request->validated();

        try {
            $mappedActor = $this->sceneConflictActorInputMapper->mapCombatActor($scene, $data);
            $mappedTarget = $this->sceneConflictActorInputMapper->mapCombatTarget($scene, $data);

            /** @var CombatActor $combatActor */
            $combatActor = $mappedActor['actor'];
            /** @var CombatTarget $combatTarget */
            $combatTarget = $mappedTarget['actor'];

            $combatResult = $this->combatService->resolveSingleAction(
                new CombatActionInput(
                    campaign: $campaign,
                    scene: $scene,
                    actor: $combatActor,
                    target: $combatTarget,
                    weaponName: $this->resolvedString(
                        primary: $data['weapon_name'] ?? null,
                        fallback: $mappedActor['defaults']['weapon_name'] ?? null,
                    ),
                    attackTargetValue: $this->resolvedInt(
                        primary: $data['attack_target_value'] ?? null,
                        fallback: $mappedActor['defaults']['attack_target_value'] ?? null,
                        minimum: 0,
                        maximum: 100,
                    ),
                    attackRollMode: (string) ($data['attack_roll_mode'] ?? 'normal'),
                    attackModifier: (int) ($data['attack_modifier'] ?? 0),
                    defenseLabel: $this->nullableString($data['defense_label'] ?? null),
                    defenseTargetValue: $this->resolvedNullableInt(
                        primary: $data['defense_target_value'] ?? null,
                        fallback: $mappedTarget['defaults']['defense_target_value'] ?? null,
                        minimum: 0,
                        maximum: 100,
                    ),
                    defenseRollMode: (string) ($data['defense_roll_mode'] ?? 'normal'),
                    defenseModifier: (int) ($data['defense_modifier'] ?? 0),
                    damage: $this->resolvedInt(
                        primary: $data['damage'] ?? null,
                        fallback: $mappedActor['defaults']['damage'] ?? null,
                        minimum: 0,
                        maximum: 999,
                    ),
                    armorProtection: $this->resolvedNullableInt(
                        primary: $data['armor_protection'] ?? null,
                        fallback: $mappedTarget['defaults']['armor_protection'] ?? null,
                        minimum: 0,
                        maximum: 99,
                    ),
                    intentText: $this->nullableString($data['intent_text'] ?? null),
                    resolutionNote: $this->nullableString($data['resolution_note'] ?? null),
                ),
            );

            /** @var SceneConflictActor|null $targetConflictActor */
            $targetConflictActor = $mappedTarget['conflict_actor'];
            $this->sceneConflictActorResultApplier->applyCombatSingleAction(
                targetConflictActor: $targetConflictActor,
                result: $combatResult,
            );
        } catch (CombatInvariantViolationException $exception) {
            report($exception);

            return redirect()
                ->to(route('campaigns.scenes.show', [
                    'world' => $world,
                    'campaign' => $campaign,
                    'scene' => $scene,
                ]).'#combat-action-tool')
                ->withInput()
                ->withErrors([
                    $this->errorFieldFromInvariant($exception->field()) => $exception->getMessage(),
                ]);
        }

        $post = $this->storeCombatPost(
            scene: $scene,
            actor: $actor,
            result: $combatResult,
            intentText: $this->nullableString($data['intent_text'] ?? null),
            resolutionNote: $this->nullableString($data['resolution_note'] ?? null),
        );

        return redirect()
            ->to(route('campaigns.scenes.show', [
                'world' => $world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]).'#post-'.$post->id)
            ->with('status', 'Kampfaktion ausgewertet und im Thread protokolliert.');
    }

    private function storeCombatPost(
        Scene $scene,
        User $actor,
        CombatActionResult $result,
        ?string $intentText,
        ?string $resolutionNote,
    ): Post {
        $content = $this->buildCombatPostContent($result, $intentText, $resolutionNote);
        $storedPost = $this->storePostService->store(
            scene: $scene,
            user: $actor,
            data: [
                'post_type' => 'ic',
                'post_mode' => 'gm',
                'character_id' => null,
                'content_format' => 'plain',
                'content' => $content,
            ],
        );

        return $storedPost->post;
    }

    private function buildCombatPostContent(
        CombatActionResult $result,
        ?string $intentText,
        ?string $resolutionNote,
    ): string {
        $attack = $result->attack;
        $defense = $result->defense;
        $outcome = $result->outcome;

        $lines = [
            '[Kampfaktion]',
            'Angreifer: '.$result->actorName,
            'Ziel: '.$result->targetName,
        ];

        if ($result->weaponName !== null && $result->weaponName !== '') {
            $lines[] = 'Waffe: '.$result->weaponName;
        }

        if ($intentText !== null && $intentText !== '') {
            $lines[] = 'Absicht: '.$intentText;
        }

        $lines[] = '';
        $lines[] = $this->formatRollLine('Angriff', $attack);

        if ((bool) ($defense['attempted'] ?? false)) {
            $defenseLabel = $this->nullableString($defense['label'] ?? null) ?? 'Verteidigung';
            $lines[] = $this->formatRollLine($defenseLabel, $defense);
        }

        if (! (bool) ($outcome['attack_hit'] ?? false)) {
            $lines[] = 'Ergebnis: Kein Treffer.';
        } elseif ((bool) ($outcome['defense_prevented_hit'] ?? false)) {
            $lines[] = 'Ergebnis: Der Treffer wird abgewehrt. Kein Schaden.';
        } else {
            $effectiveDamage = (int) ($outcome['effective_damage'] ?? 0);
            $lines[] = sprintf(
                'Schaden: %d - RS %d = %d',
                (int) ($outcome['raw_damage'] ?? 0),
                (int) ($outcome['armor_protection'] ?? 0),
                $effectiveDamage
            );

            if ($effectiveDamage > 0) {
                $lines[] = sprintf('Ergebnis: %s verliert %d LE.', $result->targetName, $effectiveDamage);
            } else {
                $lines[] = 'Ergebnis: Kein wirksamer Schaden.';
            }
        }

        $resultingLeCurrent = $outcome['resulting_le_current'] ?? null;
        $resultingLeMax = $outcome['resulting_le_max'] ?? null;
        if (is_int($resultingLeCurrent) && is_int($resultingLeMax)) {
            $lines[] = sprintf('LE: %d / %d', $resultingLeCurrent, $resultingLeMax);
        }

        if ($resolutionNote !== null && $resolutionNote !== '') {
            $lines[] = 'SL-Notiz: '.$resolutionNote;
        }

        return implode("\n", $lines);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
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

    private function errorFieldFromInvariant(string $field): string
    {
        return match ($field) {
            'actor' => 'actor_type',
            'target' => 'target_type',
            default => $field,
        };
    }

    private function resolvedInt(mixed $primary, mixed $fallback, int $minimum, int $maximum): int
    {
        $resolved = $this->resolvedNullableInt($primary, $fallback, $minimum, $maximum);
        if ($resolved === null) {
            return $minimum;
        }

        return $resolved;
    }

    private function resolvedNullableInt(mixed $primary, mixed $fallback, int $minimum, int $maximum): ?int
    {
        $value = $this->nullableInt($primary);
        if ($value === null) {
            $value = $this->nullableInt($fallback);
        }

        if ($value === null) {
            return null;
        }

        return max($minimum, min($maximum, $value));
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_int($value) || is_numeric($value) ? (int) $value : null;
    }

    private function resolvedString(mixed $primary, mixed $fallback): ?string
    {
        $value = $this->nullableString($primary);
        if ($value !== null) {
            return $value;
        }

        return $this->nullableString($fallback);
    }
}
