<?php

namespace App\Http\Controllers;

use App\Actions\Post\BuildPostThreadItemFragmentAction;
use App\Actions\Post\DeletePostAction;
use App\Actions\Post\UpdatePostAction;
use App\Actions\Post\ApplyPostModerationTransitionAction;
use App\Domain\Post\Exceptions\PostInventoryAwardInvariantViolationException;
use App\Domain\Post\Exceptions\PostProbeInvariantViolationException;
use App\Domain\Post\PostPinStateService;
use App\Domain\Post\StorePostService;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\Post\ModeratePostRequest;
use App\Http\Requests\Post\PreviewPostRequest;
use App\Http\Requests\Post\StorePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use App\Support\PostContentRenderer;
use App\Support\SensitiveFeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

class PostController extends Controller
{
    use EnsuresWorldContext;

    public function __construct(
        private readonly StorePostService $storePostService,
        private readonly UpdatePostAction $updatePostAction,
        private readonly DeletePostAction $deletePostAction,
        private readonly ApplyPostModerationTransitionAction $applyPostModerationTransitionAction,
        private readonly PostPinStateService $postPinStateService,
        private readonly BuildPostThreadItemFragmentAction $buildPostThreadItemFragmentAction,
    ) {}

    public function store(StorePostRequest $request, World $world, Campaign $campaign, Scene $scene): RedirectResponse
    {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('create', [Post::class, $scene]);

        $data = $request->validated();
        $user = $this->authenticatedUser($request);

        try {
            $storedPost = $this->storePostService->store(
                scene: $scene,
                user: $user,
                data: $data,
            );
        } catch (PostProbeInvariantViolationException|PostInventoryAwardInvariantViolationException $exception) {
            report($exception);

            return redirect()
                ->to(route('campaigns.scenes.show', ['world' => $world, 'campaign' => $campaign, 'scene' => $scene]))
                ->withInput()
                ->withErrors([
                    $exception->field() => $exception->getMessage(),
                ]);
        }

        $statusMessage = 'Beitrag gespeichert.';

        if ($storedPost->probeCreated && $storedPost->inventoryAwardApplied) {
            $statusMessage = 'Beitrag, Probe und Inventar-Fund gespeichert.';
        } elseif ($storedPost->probeCreated) {
            $statusMessage = 'Beitrag und Probe gespeichert.';
        } elseif ($storedPost->inventoryAwardApplied) {
            $statusMessage = 'Beitrag und Inventar-Fund gespeichert.';
        }

        $postType = (string) ($data['post_type'] ?? 'ic');
        $postFeedback = [
            'kind' => $postType === 'ooc' ? 'ooc' : 'ic',
            'title' => $postType === 'ooc'
                ? 'Meta-Kanal aktualisiert'
                : 'Szenenabschnitt fortgeschrieben',
            'note' => $postType === 'ooc'
                ? 'Absprachen wurden im Meta-Kanal verankert.'
                : 'Die Erzählung fließt weiter im Abenteuerkanal.',
        ];

        return redirect()
            ->to(route('campaigns.scenes.show', ['world' => $world, 'campaign' => $campaign, 'scene' => $scene]).'#post-'.$storedPost->post->id)
            ->with('status', $statusMessage)
            ->with('post_feedback', $postFeedback);
    }

    public function edit(World $world, Post $post): View
    {
        $post->loadMissing(Post::WORLD_CONTEXT_RELATIONS);
        $this->ensurePostBelongsToWorld($world, $post);
        $this->authorize('update', $post);

        $post->load([...Post::SCENE_CONTEXT_RELATIONS, 'user', 'character', 'media']);
        [$scene, $campaign] = $this->resolveSceneContext($post);

        $characterOwner = $post->user_id === (int) auth()->id()
            ? auth()->user()
            : $post->user;

        if (! $characterOwner instanceof User) {
            abort(403);
        }

        $characters = $characterOwner
            ->characters()
            ->where('world_id', (int) $campaign->world_id)
            ->orderBy('name')
            ->get();

        return view('posts.edit', compact('world', 'post', 'scene', 'campaign', 'characters'));
    }

    public function update(UpdatePostRequest $request, World $world, Post $post): RedirectResponse
    {
        $post->loadMissing(Post::WORLD_CONTEXT_RELATIONS);
        $this->ensurePostBelongsToWorld($world, $post);
        $this->authorize('update', $post);

        $data = $request->validated();
        $user = $this->authenticatedUser($request);
        /** @var array{
         *   post_type: string,
         *   post_mode?: mixed,
         *   character_id?: mixed,
         *   content_format: string,
         *   content: string,
         *   ic_quote?: mixed,
         *   moderation_status?: mixed,
         *   moderation_note?: mixed
         * } $updateData
         */
        $updateData = $data;
        $this->updatePostAction->execute($post, $user, $updateData);

        $post->load(Post::SCENE_CONTEXT_RELATIONS);
        [$scene, $campaign] = $this->resolveSceneContext($post);

        return redirect()
            ->to(route('campaigns.scenes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]).'#post-'.$post->id)
            ->with('status', 'Beitrag aktualisiert.');
    }

    public function destroy(Request $request, World $world, Post $post): RedirectResponse
    {
        $post->loadMissing(Post::WORLD_CONTEXT_RELATIONS);
        $this->ensurePostBelongsToWorld($world, $post);
        $this->authorize('delete', $post);
        $actor = $this->authenticatedUser($request);

        $post->load(Post::SCENE_CONTEXT_RELATIONS);
        [$scene, $campaign] = $this->resolveSceneContext($post);

        $this->deletePostAction->execute(
            $post,
            (int) $actor->id,
        );

        return redirect()
            ->route('campaigns.scenes.show', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'scene' => $scene,
            ])
            ->with('status', 'Beitrag gelöscht.');
    }

    public function moderate(ModeratePostRequest $request, World $world, Post $post): View|RedirectResponse
    {
        $post->loadMissing(Post::WORLD_CONTEXT_RELATIONS);
        $this->ensurePostBelongsToWorld($world, $post);
        $this->authorize('moderate', $post);

        $moderator = $this->authenticatedUser($request);
        $status = $request->validated('moderation_status');
        $this->applyPostModerationTransitionAction->execute(
            post: $post,
            moderator: $moderator,
            targetStatus: $status,
            moderationNote: (string) $request->validated('moderation_note', ''),
        );

        if ($this->expectsThreadItemFragment($request)) {
            return $this->buildPostThreadItemFragmentAction->execute($post, $moderator);
        }

        return back()->with('status', 'Moderationsstatus aktualisiert.');
    }

    public function pin(Request $request, World $world, Post $post): View|RedirectResponse
    {
        $post->loadMissing(Post::WORLD_CONTEXT_RELATIONS);
        $this->ensurePostBelongsToWorld($world, $post);
        $this->authorize('moderate', $post);
        $actor = $this->authenticatedUser($request);
        $pinnedById = (int) $actor->id;

        $this->postPinStateService->setPinState(
            post: $post,
            isPinned: true,
            pinnedByUserId: $pinnedById,
        );

        if ($request->header('HX-Request') === 'true') {
            return $this->buildPostThreadItemFragmentAction->execute($post, $actor);
        }

        return back()->with('status', 'Beitrag angepinnt.');
    }

    public function unpin(Request $request, World $world, Post $post): View|RedirectResponse
    {
        $post->loadMissing(Post::WORLD_CONTEXT_RELATIONS);
        $this->ensurePostBelongsToWorld($world, $post);
        $this->authorize('moderate', $post);
        $actor = $this->authenticatedUser($request);

        $this->postPinStateService->setPinState(
            post: $post,
            isPinned: false,
        );

        if ($request->header('HX-Request') === 'true') {
            return $this->buildPostThreadItemFragmentAction->execute($post, $actor);
        }

        return back()->with('status', 'Pin entfernt.');
    }

    public function preview(PreviewPostRequest $request, World $world): JsonResponse
    {
        abort_unless(SensitiveFeatureGate::enabled('features.wave3.editor_preview', false), 404);

        $data = $request->validated();

        $html = app(PostContentRenderer::class)
            ->render((string) ($data['content'] ?? ''), (string) $data['content_format'])
            ->toHtml();

        return response()->json([
            'status' => 'ok',
            'html' => $html,
        ]);
    }

    /**
     * @return array{Scene, Campaign}
     */
    private function resolveSceneContext(Post $post): array
    {
        /** @var Scene $scene */
        $scene = $post->scene;
        /** @var Campaign $campaign */
        $campaign = $scene->campaign;

        return [$scene, $campaign];
    }

    private function expectsThreadItemFragment(Request $request): bool
    {
        if ($request->header('HX-Request') !== 'true') {
            return false;
        }

        $hxTarget = trim((string) $request->header('HX-Target', ''));

        return str_starts_with($hxTarget, 'post-');
    }
}
