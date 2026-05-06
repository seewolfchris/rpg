<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Post\AttachCombatPhaseResultMetaAction;
use App\Domain\Combat\CombatPhasePostRenderer;
use App\Domain\Combat\CombatPhaseService;
use App\Domain\Combat\Data\CombatActionInput;
use App\Domain\Combat\Data\CombatActor;
use App\Domain\Combat\Data\CombatPhaseResolutionResult;
use App\Domain\Combat\Data\CombatTarget;
use App\Domain\Combat\Exceptions\CombatInvariantViolationException;
use App\Domain\Post\StorePostService;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\Scene\ResolveSceneCombatPhaseRequest;
use App\Http\Requests\Scene\StoreSceneCombatPhaseActionRequest;
use App\Http\Requests\Scene\StoreSceneCombatPhaseRequest;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\CombatPhase;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use App\Support\SensitiveFeatureGate;
use Illuminate\Http\RedirectResponse;

class SceneCombatPhaseController extends Controller
{
    use EnsuresWorldContext;

    public function __construct(
        private readonly CombatPhaseService $combatPhaseService,
        private readonly StorePostService $storePostService,
        private readonly CombatPhasePostRenderer $combatPhasePostRenderer,
        private readonly AttachCombatPhaseResultMetaAction $attachCombatPhaseResultMetaAction,
    ) {}

    public function store(
        StoreSceneCombatPhaseRequest $request,
        World $world,
        Campaign $campaign,
        Scene $scene,
    ): RedirectResponse {
        abort_unless(SensitiveFeatureGate::enabled('features.combat_tools_enabled', false), 404);
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('view', $scene);

        $actor = $this->authenticatedUser($request);
        abort_unless($campaign->canModeratePosts($actor), 403);

        try {
            $phase = $this->combatPhaseService->startPhase($campaign, $scene, $actor);
        } catch (CombatInvariantViolationException $exception) {
            report($exception);

            return redirect()
                ->to($this->sceneUrl($world, $campaign, $scene, '#combat-phase-tool'))
                ->withInput()
                ->withErrors([
                    $this->errorFieldFromInvariant($exception->field()) => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->to($this->sceneUrl($world, $campaign, $scene, '#combat-phase-tool'))
            ->with('status', 'Kampfphase '.$phase->phase_number.' wurde gestartet.');
    }

    public function storeAction(
        StoreSceneCombatPhaseActionRequest $request,
        World $world,
        Campaign $campaign,
        Scene $scene,
        CombatPhase $combatPhase,
    ): RedirectResponse {
        abort_unless(SensitiveFeatureGate::enabled('features.combat_tools_enabled', false), 404);
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->ensureCombatPhaseBelongsToScene($campaign, $scene, $combatPhase);
        $this->authorize('view', $scene);

        $actor = $this->authenticatedUser($request);
        abort_unless($campaign->canModeratePosts($actor), 403);

        $data = $request->validated();

        try {
            $action = $this->combatPhaseService->queueAction(
                phase: $combatPhase,
                input: new CombatActionInput(
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
                ->to($this->sceneUrl($world, $campaign, $scene, '#combat-phase-tool'))
                ->withInput()
                ->withErrors([
                    $this->errorFieldFromInvariant($exception->field()) => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->to($this->sceneUrl($world, $campaign, $scene, '#combat-phase-tool'))
            ->with('status', 'Aktion #'.$action->position.' wurde zur offenen Kampfphase hinzugefügt.');
    }

    public function resolve(
        ResolveSceneCombatPhaseRequest $request,
        World $world,
        Campaign $campaign,
        Scene $scene,
        CombatPhase $combatPhase,
    ): RedirectResponse {
        abort_unless(SensitiveFeatureGate::enabled('features.combat_tools_enabled', false), 404);
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->ensureCombatPhaseBelongsToScene($campaign, $scene, $combatPhase);
        $this->authorize('view', $scene);

        $actor = $this->authenticatedUser($request);
        abort_unless($campaign->canModeratePosts($actor), 403);

        try {
            $resolution = $this->combatPhaseService->resolvePhase($combatPhase, $actor);
        } catch (CombatInvariantViolationException $exception) {
            report($exception);

            return redirect()
                ->to($this->sceneUrl($world, $campaign, $scene, '#combat-phase-tool'))
                ->withInput()
                ->withErrors([
                    $this->errorFieldFromInvariant($exception->field()) => $exception->getMessage(),
                ]);
        }

        $post = $this->storePhasePost(
            scene: $scene,
            actor: $actor,
            resolution: $resolution,
        );

        return redirect()
            ->to($this->sceneUrl($world, $campaign, $scene, '#post-'.$post->id))
            ->with('status', 'Kampfphase '.$resolution->phaseNumber.' wurde ausgewertet und protokolliert.');
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
        $snapshot = ['name' => $name];

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
        $snapshot = ['name' => $name];

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

    private function storePhasePost(Scene $scene, User $actor, CombatPhaseResolutionResult $resolution): Post
    {
        $content = $this->combatPhasePostRenderer->render($resolution);

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

        $post = $storedPost->post;
        $this->attachCombatPhaseResultMetaAction->execute(
            post: $post,
            combatPhaseResult: $resolution->toArray(),
        );

        return $post;
    }

    private function ensureCombatPhaseBelongsToScene(Campaign $campaign, Scene $scene, CombatPhase $combatPhase): void
    {
        abort_unless((int) $combatPhase->campaign_id === (int) $campaign->id, 404);
        abort_unless((int) $combatPhase->scene_id === (int) $scene->id, 404);
    }

    private function sceneUrl(World $world, Campaign $campaign, Scene $scene, string $fragment = ''): string
    {
        return route('campaigns.scenes.show', [
            'world' => $world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]).$fragment;
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
            'phase' => 'combat_phase',
            default => $field,
        };
    }
}
