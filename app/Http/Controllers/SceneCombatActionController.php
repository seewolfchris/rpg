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
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\Scene\StoreSceneCombatActionRequest;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
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
            $combatResult = $this->combatService->resolveSingleAction(
                new CombatActionInput(
                    campaign: $campaign,
                    scene: $scene,
                    actor: $this->buildActor($data),
                    target: $this->buildTarget($data),
                    weaponName: $this->nullableString($data['weapon_name'] ?? null),
                    attackTargetValue: (int) $data['attack_target_value'],
                    attackRollMode: (string) ($data['attack_roll_mode'] ?? 'normal'),
                    attackModifier: (int) ($data['attack_modifier'] ?? 0),
                    defenseLabel: $this->nullableString($data['defense_label'] ?? null),
                    defenseTargetValue: array_key_exists('defense_target_value', $data) && $data['defense_target_value'] !== null
                        ? (int) $data['defense_target_value']
                        : null,
                    defenseRollMode: (string) ($data['defense_roll_mode'] ?? 'normal'),
                    defenseModifier: (int) ($data['defense_modifier'] ?? 0),
                    damage: (int) $data['damage'],
                    armorProtection: array_key_exists('armor_protection', $data) && $data['armor_protection'] !== null
                        ? (int) $data['armor_protection']
                        : null,
                    intentText: $this->nullableString($data['intent_text'] ?? null),
                    resolutionNote: $this->nullableString($data['resolution_note'] ?? null),
                ),
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

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws CombatInvariantViolationException
     */
    private function buildActor(array $data): CombatActor
    {
        $type = (string) ($data['actor_type'] ?? '');

        if ($type === CombatActor::TYPE_CHARACTER) {
            $characterId = (int) ($data['actor_character_id'] ?? 0);
            $character = Character::query()->find($characterId);

            if (! $character instanceof Character) {
                throw CombatInvariantViolationException::actorCharacterMissing();
            }

            return CombatActor::character($character);
        }

        $name = $this->nullableString($data['actor_name'] ?? null) ?? '';
        $snapshot = [
            'name' => $name,
        ];
        if (array_key_exists('actor_le_current', $data) && $data['actor_le_current'] !== null) {
            $snapshot['le_current'] = (int) $data['actor_le_current'];
        }
        if (array_key_exists('actor_le_max', $data) && $data['actor_le_max'] !== null) {
            $snapshot['le_max'] = (int) $data['actor_le_max'];
        }

        return CombatActor::npc($name, $snapshot);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws CombatInvariantViolationException
     */
    private function buildTarget(array $data): CombatTarget
    {
        $type = (string) ($data['target_type'] ?? '');

        if ($type === CombatTarget::TYPE_CHARACTER) {
            $characterId = (int) ($data['target_character_id'] ?? 0);
            $character = Character::query()->find($characterId);

            if (! $character instanceof Character) {
                throw CombatInvariantViolationException::targetCharacterMissing();
            }

            return CombatTarget::character($character);
        }

        $name = $this->nullableString($data['target_name'] ?? null) ?? '';
        $snapshot = [
            'name' => $name,
        ];
        if (array_key_exists('target_le_current', $data) && $data['target_le_current'] !== null) {
            $snapshot['le_current'] = (int) $data['target_le_current'];
        }
        if (array_key_exists('target_le_max', $data) && $data['target_le_max'] !== null) {
            $snapshot['le_max'] = (int) $data['target_le_max'];
        }
        if (array_key_exists('armor_protection', $data) && $data['armor_protection'] !== null) {
            $snapshot['armor_protection'] = (int) $data['armor_protection'];
        }

        return CombatTarget::npc($name, $snapshot);
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
        $lines[] = sprintf(
            'Angriff: %d / %d -> %s',
            (int) ($attack['total'] ?? 0),
            (int) ($attack['target_value'] ?? 0),
            (bool) ($attack['is_success'] ?? false) ? 'Erfolg' : 'misslungen'
        );

        if ((bool) ($defense['attempted'] ?? false)) {
            $defenseLabel = $this->nullableString($defense['label'] ?? null) ?? 'Verteidigung';
            $lines[] = sprintf(
                '%s: %d / %d -> %s',
                $defenseLabel,
                (int) ($defense['total'] ?? 0),
                (int) ($defense['target_value'] ?? 0),
                (bool) ($defense['is_success'] ?? false) ? 'Erfolg' : 'misslungen'
            );
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

    private function errorFieldFromInvariant(string $field): string
    {
        return match ($field) {
            'actor' => 'actor_type',
            'target' => 'target_type',
            default => $field,
        };
    }
}
