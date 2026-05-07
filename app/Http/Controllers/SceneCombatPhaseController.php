<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Post\AttachCombatPhaseResultMetaAction;
use App\Domain\Combat\CombatPhasePostRenderer;
use App\Domain\Combat\CombatPhaseService;
use App\Domain\Combat\Data\CombatActionInput;
use App\Domain\Combat\Data\CombatPhaseResolutionResult;
use App\Domain\Combat\Exceptions\CombatInvariantViolationException;
use App\Domain\Post\StorePostService;
use App\Domain\SceneConflict\SceneConflictActorInputMapper;
use App\Domain\SceneConflict\SceneConflictActorResultApplier;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\Scene\ResolveSceneCombatPhaseRequest;
use App\Http\Requests\Scene\StoreSceneCombatPhaseActionRequest;
use App\Http\Requests\Scene\StoreSceneCombatPhaseRequest;
use App\Models\Campaign;
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
        private readonly SceneConflictActorInputMapper $sceneConflictActorInputMapper,
        private readonly SceneConflictActorResultApplier $sceneConflictActorResultApplier,
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
            $mappedActor = $this->sceneConflictActorInputMapper->mapCombatActor($scene, $data);
            $mappedTarget = $this->sceneConflictActorInputMapper->mapCombatTarget($scene, $data);
            /** @var \App\Domain\Combat\Data\CombatActor $combatActor */
            $combatActor = $mappedActor['actor'];
            /** @var \App\Domain\Combat\Data\CombatTarget $combatTarget */
            $combatTarget = $mappedTarget['actor'];

            $action = $this->combatPhaseService->queueAction(
                phase: $combatPhase,
                input: new CombatActionInput(
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
            $this->sceneConflictActorResultApplier->applyCombatPhaseResolution($combatPhase, $resolution);
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
