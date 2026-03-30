<?php

namespace App\Http\Controllers;

use App\Domain\Post\PostMentionNotificationService;
use App\Domain\Post\PostModerationService;
use App\Domain\Post\Exceptions\PostInventoryAwardInvariantViolationException;
use App\Domain\Post\Exceptions\PostProbeInvariantViolationException;
use App\Domain\Post\StorePostService;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\Post\ModeratePostRequest;
use App\Http\Requests\Post\StorePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Post;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use App\Support\Gamification\PointService;
use App\Support\PostContentRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PostController extends Controller
{
    use EnsuresWorldContext;

    public function __construct(
        private readonly PointService $pointService,
        private readonly StorePostService $storePostService,
        private readonly PostModerationService $postModerationService,
        private readonly PostMentionNotificationService $postMentionNotificationService,
    ) {}

    public function store(StorePostRequest $request, World $world, Campaign $campaign, Scene $scene): RedirectResponse
    {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('create', [Post::class, $scene]);

        $data = $request->validated();
        $user = $request->user();
        $isModerator = $this->canModerateScene($user, $scene);
        $requiresApproval = $campaign->requiresPostModeration()
            && ! $campaign->userCanPostWithoutModeration($user)
            && ! $isModerator;

        try {
            $storedPost = $this->storePostService->store(
                scene: $scene,
                user: $user,
                data: $data,
                isModerator: $isModerator,
                requiresApproval: $requiresApproval,
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

        $post->load([...Post::SCENE_CONTEXT_RELATIONS, 'user', 'character']);
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
        $user = $request->user();
        /** @var Scene $scene */
        $scene = $post->scene;
        /** @var Campaign $campaign */
        $campaign = $scene->campaign;
        $isModerator = $user->can('moderate', $post);
        $requiresApproval = $campaign->requiresPostModeration()
            && ! $campaign->userCanPostWithoutModeration($user)
            && ! $isModerator;
        $previousModerationStatus = (string) $post->moderation_status;
        $moderationNote = $isModerator
            ? $this->normalizeModerationNote((string) ($data['moderation_note'] ?? ''))
            : null;

        $moderationStatus = $requiresApproval ? 'pending' : 'approved';
        $approvedAt = $requiresApproval ? null : Carbon::now();
        $approvedBy = null;

        if ($isModerator && isset($data['moderation_status'])) {
            $moderationStatus = $data['moderation_status'];

            if ($moderationStatus === 'approved') {
                $approvedAt = Carbon::now();
                $approvedBy = $user->id;
            }
        }

        $nextCharacterId = $data['post_type'] === 'ic'
            ? (int) $data['character_id']
            : null;
        /** @var array<string, mixed> $nextMeta */
        $nextMeta = (array) ($post->meta ?? []);
        $nextIcQuote = trim((string) ($data['ic_quote'] ?? ''));

        if ($data['post_type'] === 'ic' && $nextIcQuote !== '') {
            $nextMeta['ic_quote'] = $nextIcQuote;
        } else {
            unset($nextMeta['ic_quote']);
        }

        $normalizedNextMeta = $nextMeta !== [] ? $nextMeta : null;

        $hasContentChange = $this->hasTrackedChanges($post, [
            'character_id' => $nextCharacterId,
            'post_type' => $data['post_type'],
            'content_format' => $data['content_format'],
            'content' => $data['content'],
            'meta' => $normalizedNextMeta,
        ]);

        if ($hasContentChange) {
            $this->createRevisionSnapshot($post, $user);
        }

        $post->update([
            'character_id' => $nextCharacterId,
            'post_type' => $data['post_type'],
            'content_format' => $data['content_format'],
            'content' => $data['content'],
            'meta' => $normalizedNextMeta,
            'moderation_status' => $moderationStatus,
            'approved_at' => $approvedAt,
            'approved_by' => $approvedBy,
            'is_edited' => $hasContentChange ? true : $post->is_edited,
            'edited_at' => $hasContentChange ? now() : $post->edited_at,
        ]);

        $this->postModerationService->synchronize(
            post: $post,
            moderator: $isModerator ? $user : null,
            previousStatus: $previousModerationStatus,
            moderationNote: $moderationNote,
        );

        if ($hasContentChange) {
            $this->postMentionNotificationService->notifyMentions($post, $user);
        }

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

    public function destroy(World $world, Post $post): RedirectResponse
    {
        $post->loadMissing(Post::WORLD_CONTEXT_RELATIONS);
        $this->ensurePostBelongsToWorld($world, $post);
        $this->authorize('delete', $post);

        $post->load(Post::SCENE_CONTEXT_RELATIONS);
        [$scene, $campaign] = $this->resolveSceneContext($post);

        $this->pointService->revokeApprovedPostPoints($post);
        $post->delete();

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

        $status = $request->validated('moderation_status');
        $moderationNote = $this->normalizeModerationNote((string) $request->validated('moderation_note', ''));
        $user = $request->user();
        /** @var int<0, max> $moderatorId */
        $moderatorId = max(0, (int) $user->id);
        $previousModerationStatus = (string) $post->moderation_status;

        $post->moderation_status = $status;

        if ($status === 'approved') {
            $post->approved_at = now();
            $post->approved_by = $moderatorId;
        } else {
            $post->approved_at = null;
            $post->approved_by = null;
        }

        $post->save();

        $this->postModerationService->synchronize(
            post: $post,
            moderator: $user,
            previousStatus: $previousModerationStatus,
            moderationNote: $moderationNote,
        );

        if ($this->expectsThreadItemFragment($request)) {
            return $this->threadItemFragment($post);
        }

        return back()->with('status', 'Moderationsstatus aktualisiert.');
    }

    public function pin(Request $request, World $world, Post $post): View|RedirectResponse
    {
        $post->loadMissing(Post::WORLD_CONTEXT_RELATIONS);
        $this->ensurePostBelongsToWorld($world, $post);
        $this->authorize('moderate', $post);

        $post->is_pinned = true;
        $post->pinned_at = now();
        $pinnedById = auth()->id();
        $post->pinned_by = $pinnedById === null ? null : max(0, (int) $pinnedById);
        $post->save();

        if ($request->header('HX-Request') === 'true') {
            return $this->threadItemFragment($post);
        }

        return back()->with('status', 'Beitrag angepinnt.');
    }

    public function unpin(Request $request, World $world, Post $post): View|RedirectResponse
    {
        $post->loadMissing(Post::WORLD_CONTEXT_RELATIONS);
        $this->ensurePostBelongsToWorld($world, $post);
        $this->authorize('moderate', $post);

        $post->is_pinned = false;
        $post->pinned_at = null;
        $post->pinned_by = null;
        $post->save();

        if ($request->header('HX-Request') === 'true') {
            return $this->threadItemFragment($post);
        }

        return back()->with('status', 'Pin entfernt.');
    }

    public function preview(Request $request, World $world): JsonResponse
    {
        abort_unless((bool) config('features.wave3.editor_preview', false), 404);

        $data = $request->validate([
            'content_format' => ['required', Rule::in(['markdown'])],
            'content' => ['nullable', 'string', 'max:10000'],
        ]);

        $html = app(PostContentRenderer::class)
            ->render((string) ($data['content'] ?? ''), (string) $data['content_format'])
            ->toHtml();

        return response()->json([
            'status' => 'ok',
            'html' => $html,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function hasTrackedChanges(Post $post, array $attributes): bool
    {
        foreach ($attributes as $key => $value) {
            if ($post->{$key} !== $value) {
                return true;
            }
        }

        return false;
    }

    private function createRevisionSnapshot(Post $post, User $editor): void
    {
        DB::transaction(function () use ($post, $editor): void {
            $lockedPost = Post::query()
                ->whereKey($post->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $nextVersion = ((int) $lockedPost->revisions()->max('version')) + 1;

            $lockedPost->revisions()->create([
                'version' => $nextVersion,
                'editor_id' => $editor->id,
                'character_id' => $lockedPost->character_id,
                'post_type' => $lockedPost->post_type,
                'content_format' => $lockedPost->content_format,
                'content' => $lockedPost->content,
                'meta' => $lockedPost->meta,
                'moderation_status' => $lockedPost->moderation_status,
                'created_at' => now(),
            ]);
        }, 3);
    }

    private function canModerateScene(User $user, Scene $scene): bool
    {
        if ($user->isGmOrAdmin()) {
            return true;
        }

        $campaign = $this->resolveCampaignFromScene($scene);

        return $campaign?->isCoGm($user) ?? false;
    }

    private function resolveCampaignFromScene(Scene $scene): ?Campaign
    {
        $campaign = $scene->campaign;

        return $campaign instanceof Campaign ? $campaign : null;
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

    private function normalizeModerationNote(string $note): ?string
    {
        $normalized = trim($note);

        return $normalized !== '' ? $normalized : null;
    }

    private function expectsThreadItemFragment(Request $request): bool
    {
        if ($request->header('HX-Request') !== 'true') {
            return false;
        }

        $hxTarget = trim((string) $request->header('HX-Target', ''));

        return str_starts_with($hxTarget, 'post-');
    }

    private function threadItemFragment(Post $post): View
    {
        $post->load(Post::THREAD_ITEM_RELATIONS);

        /** @var Scene $scene */
        $scene = $post->scene;
        /** @var Campaign $campaign */
        $campaign = $scene->campaign;
        $bookmarkCountForNav = $this->visibleBookmarkCountForUser(auth()->user());

        return view('posts._thread-item', compact('post', 'scene', 'campaign', 'bookmarkCountForNav'));
    }

    private function visibleBookmarkCountForUser(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        return (int) $user->sceneBookmarks()
            ->whereHas('scene.campaign', function (Builder $campaignQuery) use ($user): void {
                if ($user->isGmOrAdmin()) {
                    return;
                }

                $campaignQuery->where(function (Builder $innerQuery) use ($user): void {
                    $innerQuery
                        ->where('is_public', true)
                        ->orWhere('owner_id', $user->id)
                        ->orWhereHas('invitations', function (Builder $invitationQuery) use ($user): void {
                            $invitationQuery
                                ->where('user_id', $user->id)
                                ->where('status', CampaignInvitation::STATUS_ACCEPTED);
                        });
                });
            })
            ->count();
    }
}
