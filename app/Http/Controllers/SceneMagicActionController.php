<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Post\AttachMagicResultMetaAction;
use App\Domain\Magic\Data\MagicActionInput;
use App\Domain\Magic\Data\MagicActionResult;
use App\Domain\Magic\Data\MagicActor;
use App\Domain\Magic\Data\MagicTarget;
use App\Domain\Magic\Exceptions\MagicInvariantViolationException;
use App\Domain\Magic\MagicResultPostRenderer;
use App\Domain\Magic\MagicService;
use App\Domain\Post\StorePostService;
use App\Domain\SceneConflict\SceneConflictActorInputMapper;
use App\Domain\SceneConflict\SceneConflictActorResultApplier;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\Scene\StoreSceneMagicActionRequest;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneConflictActor;
use App\Models\User;
use App\Models\World;
use App\Support\SensitiveFeatureGate;
use Illuminate\Http\RedirectResponse;

class SceneMagicActionController extends Controller
{
    use EnsuresWorldContext;

    public function __construct(
        private readonly MagicService $magicService,
        private readonly StorePostService $storePostService,
        private readonly MagicResultPostRenderer $magicResultPostRenderer,
        private readonly AttachMagicResultMetaAction $attachMagicResultMetaAction,
        private readonly SceneConflictActorInputMapper $sceneConflictActorInputMapper,
        private readonly SceneConflictActorResultApplier $sceneConflictActorResultApplier,
    ) {}

    public function store(
        StoreSceneMagicActionRequest $request,
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
            $mappedActor = $this->sceneConflictActorInputMapper->mapMagicActor($scene, $data);
            $mappedTarget = $this->sceneConflictActorInputMapper->mapMagicTarget($scene, $data);

            /** @var MagicActor $magicActor */
            $magicActor = $mappedActor['actor'];
            /** @var MagicTarget $magicTarget */
            $magicTarget = $mappedTarget['actor'];

            $magicResult = $this->magicService->resolveSingleAction(
                new MagicActionInput(
                    campaign: $campaign,
                    scene: $scene,
                    actor: $magicActor,
                    target: $magicTarget,
                    spellName: (string) ($data['spell_name'] ?? ''),
                    spellTargetValue: $this->resolvedInt(
                        primary: $data['spell_target_value'] ?? null,
                        fallback: $mappedActor['defaults']['spell_target_value'] ?? null,
                        minimum: 0,
                        maximum: 100,
                    ),
                    spellRollMode: (string) ($data['spell_roll_mode'] ?? 'normal'),
                    spellModifier: (int) ($data['spell_modifier'] ?? 0),
                    aeCost: (int) ($data['ae_cost'] ?? 0),
                    defenseLabel: $this->nullableString($data['defense_label'] ?? null),
                    defenseTargetValue: $this->resolvedNullableInt(
                        primary: $data['defense_target_value'] ?? null,
                        fallback: $mappedTarget['defaults']['defense_target_value'] ?? null,
                        minimum: 0,
                        maximum: 100,
                    ),
                    defenseRollMode: (string) ($data['defense_roll_mode'] ?? 'normal'),
                    defenseModifier: (int) ($data['defense_modifier'] ?? 0),
                    effectType: (string) ($data['effect_type'] ?? MagicService::EFFECT_NARRATIVE),
                    effectAmount: (int) ($data['effect_amount'] ?? 0),
                    targetAttributeKey: $this->nullableString($data['target_attribute_key'] ?? null),
                    intentText: $this->nullableString($data['intent_text'] ?? null),
                    resolutionNote: $this->nullableString($data['resolution_note'] ?? null),
                ),
            );

            /** @var SceneConflictActor|null $actorConflictActor */
            $actorConflictActor = $mappedActor['conflict_actor'];
            /** @var SceneConflictActor|null $targetConflictActor */
            $targetConflictActor = $mappedTarget['conflict_actor'];

            $this->sceneConflictActorResultApplier->applyMagicSingleAction(
                actorConflictActor: $actorConflictActor,
                targetConflictActor: $targetConflictActor,
                result: $magicResult,
            );
        } catch (MagicInvariantViolationException $exception) {
            report($exception);

            return redirect()
                ->to($this->sceneUrl($world, $campaign, $scene, '#magic-action-tool'))
                ->withInput()
                ->withErrors([
                    $this->errorFieldFromInvariant($exception->field()) => $exception->getMessage(),
                ]);
        }

        $post = $this->storeMagicPost(
            scene: $scene,
            actor: $actor,
            result: $magicResult,
        );

        return redirect()
            ->to($this->sceneUrl($world, $campaign, $scene, '#post-'.$post->id))
            ->with('status', 'Magieaktion ausgewertet und im Thread protokolliert.');
    }

    private function storeMagicPost(Scene $scene, User $actor, MagicActionResult $result): Post
    {
        $content = $this->magicResultPostRenderer->render($result);

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
        $this->attachMagicResultMetaAction->execute($post, $result->toArray());

        return $post;
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
}
