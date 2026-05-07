<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\SceneConflict\Exceptions\SceneConflictActorInvariantViolationException;
use App\Domain\SceneConflict\SceneConflictActorService;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\Scene\StoreSceneConflictActorRequest;
use App\Http\Requests\Scene\UpdateSceneConflictActorRequest;
use App\Models\Campaign;
use App\Models\Character;
use App\Models\Scene;
use App\Models\SceneConflictActor;
use App\Models\World;
use App\Support\SensitiveFeatureGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SceneConflictActorController extends Controller
{
    use EnsuresWorldContext;

    public function __construct(
        private readonly SceneConflictActorService $sceneConflictActorService,
    ) {}

    public function store(
        StoreSceneConflictActorRequest $request,
        World $world,
        Campaign $campaign,
        Scene $scene,
    ): RedirectResponse {
        abort_unless(SensitiveFeatureGate::enabled('features.combat_tools_enabled', false), 404);
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('view', $scene);

        $actorUser = $this->authenticatedUser($request);
        abort_unless($campaign->canModeratePosts($actorUser), 403);

        $data = $request->validated();
        $actorType = (string) ($data['actor_type'] ?? '');

        try {
            if ($actorType === SceneConflictActor::TYPE_CHARACTER) {
                $characterId = (int) ($data['character_id'] ?? 0);
                $character = Character::query()->find($characterId);

                if (! $character instanceof Character) {
                    return redirect()
                        ->to($this->sceneUrl($world, $campaign, $scene, '#conflict-actors-tool'))
                        ->withInput()
                        ->withErrors(['character_id' => 'Der Charakter konnte nicht gefunden werden.']);
                }

                $this->sceneConflictActorService->addCharacterActor(
                    campaign: $campaign,
                    scene: $scene,
                    character: $character,
                    sortOrder: array_key_exists('sort_order', $data) && $data['sort_order'] !== null
                        ? (int) $data['sort_order']
                        : null,
                );
            } else {
                $this->sceneConflictActorService->addNpcActor(
                    campaign: $campaign,
                    scene: $scene,
                    payload: $data,
                );
            }
        } catch (SceneConflictActorInvariantViolationException $exception) {
            report($exception);

            return redirect()
                ->to($this->sceneUrl($world, $campaign, $scene, '#conflict-actors-tool'))
                ->withInput()
                ->withErrors([
                    $this->errorFieldFromInvariant($exception->field()) => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->to($this->sceneUrl($world, $campaign, $scene, '#conflict-actors-tool'))
            ->with('status', 'Beteiligter gespeichert.');
    }

    public function update(
        UpdateSceneConflictActorRequest $request,
        World $world,
        Campaign $campaign,
        Scene $scene,
        SceneConflictActor $sceneConflictActor,
    ): RedirectResponse {
        abort_unless(SensitiveFeatureGate::enabled('features.combat_tools_enabled', false), 404);
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->ensureConflictActorBelongsToScene($campaign, $scene, $sceneConflictActor);
        $this->authorize('view', $scene);

        $actorUser = $this->authenticatedUser($request);
        abort_unless($campaign->canModeratePosts($actorUser), 403);

        try {
            $this->sceneConflictActorService->updateNpcActor(
                actor: $sceneConflictActor,
                payload: $request->validated(),
            );
        } catch (SceneConflictActorInvariantViolationException $exception) {
            report($exception);

            return redirect()
                ->to($this->sceneUrl($world, $campaign, $scene, '#conflict-actors-tool'))
                ->withInput()
                ->withErrors([
                    $this->errorFieldFromInvariant($exception->field()) => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->to($this->sceneUrl($world, $campaign, $scene, '#conflict-actors-tool'))
            ->with('status', 'NPC-Beteiligter aktualisiert.');
    }

    public function destroy(
        Request $request,
        World $world,
        Campaign $campaign,
        Scene $scene,
        SceneConflictActor $sceneConflictActor,
    ): RedirectResponse {
        abort_unless(SensitiveFeatureGate::enabled('features.combat_tools_enabled', false), 404);
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->ensureConflictActorBelongsToScene($campaign, $scene, $sceneConflictActor);
        $this->authorize('view', $scene);

        $actorUser = $this->authenticatedUser($request);
        abort_unless($campaign->canModeratePosts($actorUser), 403);

        $this->sceneConflictActorService->removeActor($sceneConflictActor);

        return redirect()
            ->to($this->sceneUrl($world, $campaign, $scene, '#conflict-actors-tool'))
            ->with('status', 'Beteiligter entfernt.');
    }

    private function ensureConflictActorBelongsToScene(
        Campaign $campaign,
        Scene $scene,
        SceneConflictActor $sceneConflictActor,
    ): void {
        abort_unless((int) $sceneConflictActor->campaign_id === (int) $campaign->id, 404);
        abort_unless((int) $sceneConflictActor->scene_id === (int) $scene->id, 404);
    }

    private function sceneUrl(World $world, Campaign $campaign, Scene $scene, string $fragment = ''): string
    {
        return route('campaigns.scenes.show', [
            'world' => $world,
            'campaign' => $campaign,
            'scene' => $scene,
        ]).$fragment;
    }

    private function errorFieldFromInvariant(string $field): string
    {
        return match ($field) {
            'scene_conflict_actor' => 'conflict_actor',
            default => $field,
        };
    }
}

