<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post\ModeratePostRequest;
use App\Http\Requests\Post\StorePostRequest;
use App\Http\Requests\Post\UpdatePostRequest;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\PostModerationLog;
use App\Models\Scene;
use App\Models\SceneSubscription;
use App\Models\User;
use App\Notifications\PostModerationStatusNotification;
use App\Notifications\SceneNewPostNotification;
use App\Support\Gamification\PointService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class PostController extends Controller
{
    public function __construct(
        private readonly PointService $pointService,
    ) {}

    public function store(StorePostRequest $request, Campaign $campaign, Scene $scene): RedirectResponse
    {
        $this->ensureSceneBelongsToCampaign($campaign, $scene);
        $this->authorize('create', [Post::class, $scene]);

        $data = $request->validated();
        $user = $request->user();
        $isModerator = $this->canModerateScene($user, $scene);

        $post = Post::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'character_id' => $data['post_type'] === 'ic' ? $data['character_id'] : null,
            'post_type' => $data['post_type'],
            'content_format' => $data['content_format'],
            'content' => $data['content'],
            'meta' => null,
            'moderation_status' => $isModerator ? 'approved' : 'pending',
            'approved_at' => $isModerator ? now() : null,
            'approved_by' => $isModerator ? $user->id : null,
        ]);

        $this->ensureAuthorSubscription($post, $user);
        $this->pointService->syncApprovedPost($post);
        $this->notifySceneParticipantsAboutNewPost($post, $user);

        return redirect()
            ->to(route('campaigns.scenes.show', [$campaign, $scene]).'#post-'.$post->id)
            ->with('status', 'Beitrag gespeichert.');
    }

    public function edit(Post $post): View
    {
        $this->authorize('update', $post);

        $post->load(['scene.campaign', 'user', 'character']);

        $characterOwner = $post->user_id === (int) auth()->id()
            ? auth()->user()
            : $post->user;

        $characters = $characterOwner
            ->characters()
            ->orderBy('name')
            ->get();

        return view('posts.edit', compact('post', 'characters'));
    }

    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        $this->authorize('update', $post);

        $data = $request->validated();
        $user = $request->user();
        $isModerator = $user->can('moderate', $post);
        $previousModerationStatus = (string) $post->moderation_status;
        $moderationNote = $isModerator
            ? $this->normalizeModerationNote((string) ($data['moderation_note'] ?? ''))
            : null;

        $moderationStatus = 'pending';
        $approvedAt = null;
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

        $hasContentChange = $this->hasTrackedChanges($post, [
            'character_id' => $nextCharacterId,
            'post_type' => $data['post_type'],
            'content_format' => $data['content_format'],
            'content' => $data['content'],
        ]);

        if ($hasContentChange) {
            $this->createRevisionSnapshot($post, $user);
        }

        $post->update([
            'character_id' => $nextCharacterId,
            'post_type' => $data['post_type'],
            'content_format' => $data['content_format'],
            'content' => $data['content'],
            'moderation_status' => $moderationStatus,
            'approved_at' => $approvedAt,
            'approved_by' => $approvedBy,
            'is_edited' => $hasContentChange ? true : $post->is_edited,
            'edited_at' => $hasContentChange ? now() : $post->edited_at,
        ]);

        $this->createModerationAuditEntry(
            post: $post,
            moderator: $isModerator ? $user : null,
            previousStatus: $previousModerationStatus,
            newStatus: $moderationStatus,
            reason: $moderationNote,
        );
        $this->pointService->syncApprovedPost($post);
        $this->notifyAuthorAboutModerationChange($post, $previousModerationStatus, $user, $moderationNote);

        $post->load('scene.campaign', 'scene');

        return redirect()
            ->to(route('campaigns.scenes.show', [$post->scene->campaign, $post->scene]).'#post-'.$post->id)
            ->with('status', 'Beitrag aktualisiert.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        $post->load('scene.campaign', 'scene');
        $campaign = $post->scene->campaign;
        $scene = $post->scene;

        $this->pointService->revokeApprovedPostPoints($post);
        $post->delete();

        return redirect()
            ->route('campaigns.scenes.show', [$campaign, $scene])
            ->with('status', 'Beitrag geloescht.');
    }

    public function moderate(ModeratePostRequest $request, Post $post): RedirectResponse
    {
        $this->authorize('moderate', $post);

        $status = $request->validated('moderation_status');
        $moderationNote = $this->normalizeModerationNote((string) $request->validated('moderation_note', ''));
        $user = $request->user();
        $previousModerationStatus = (string) $post->moderation_status;

        $post->moderation_status = $status;

        if ($status === 'approved') {
            $post->approved_at = now();
            $post->approved_by = $user->id;
        } else {
            $post->approved_at = null;
            $post->approved_by = null;
        }

        $post->save();
        $this->createModerationAuditEntry(
            post: $post,
            moderator: $user,
            previousStatus: $previousModerationStatus,
            newStatus: (string) $post->moderation_status,
            reason: $moderationNote,
        );
        $this->pointService->syncApprovedPost($post);
        $this->notifyAuthorAboutModerationChange($post, $previousModerationStatus, $user, $moderationNote);

        return back()->with('status', 'Moderationsstatus aktualisiert.');
    }

    public function pin(Post $post): RedirectResponse
    {
        $this->authorize('moderate', $post);

        $post->is_pinned = true;
        $post->pinned_at = now();
        $post->pinned_by = auth()->id();
        $post->save();

        return back()->with('status', 'Beitrag angepinnt.');
    }

    public function unpin(Post $post): RedirectResponse
    {
        $this->authorize('moderate', $post);

        $post->is_pinned = false;
        $post->pinned_at = null;
        $post->pinned_by = null;
        $post->save();

        return back()->with('status', 'Pin entfernt.');
    }

    private function ensureSceneBelongsToCampaign(Campaign $campaign, Scene $scene): void
    {
        abort_unless($scene->campaign_id === $campaign->id, 404);
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
        $nextVersion = ((int) $post->revisions()->max('version')) + 1;

        $post->revisions()->create([
            'version' => $nextVersion,
            'editor_id' => $editor->id,
            'character_id' => $post->character_id,
            'post_type' => $post->post_type,
            'content_format' => $post->content_format,
            'content' => $post->content,
            'meta' => $post->meta,
            'moderation_status' => $post->moderation_status,
            'created_at' => now(),
        ]);
    }

    private function notifyAuthorAboutModerationChange(
        Post $post,
        string $previousStatus,
        User $moderator,
        ?string $moderationNote = null,
    ): void {
        if ($post->moderation_status === $previousStatus && ! $moderationNote) {
            return;
        }

        if ($post->user_id === $moderator->id) {
            return;
        }

        $post->loadMissing(['scene.campaign', 'user']);

        $post->user->notify(new PostModerationStatusNotification(
            post: $post,
            moderator: $moderator,
            previousStatus: $previousStatus,
            newStatus: (string) $post->moderation_status,
            moderationNote: $moderationNote,
        ));
    }

    private function notifySceneParticipantsAboutNewPost(Post $post, User $author): void
    {
        $post->loadMissing(['scene.campaign']);

        $recipientIds = SceneSubscription::query()
            ->where('scene_id', $post->scene_id)
            ->where('user_id', '!=', $author->id)
            ->where('is_muted', false)
            ->pluck('user_id')
            ->unique()
            ->values();

        if ($recipientIds->isEmpty()) {
            return;
        }

        $recipients = User::query()->whereIn('id', $recipientIds)->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new SceneNewPostNotification(
            post: $post,
            author: $author,
        ));
    }

    private function canModerateScene(User $user, Scene $scene): bool
    {
        return $user->isGmOrAdmin() || $scene->campaign->isCoGm($user);
    }

    private function normalizeModerationNote(string $note): ?string
    {
        $normalized = trim($note);

        return $normalized !== '' ? $normalized : null;
    }

    private function createModerationAuditEntry(
        Post $post,
        ?User $moderator,
        string $previousStatus,
        string $newStatus,
        ?string $reason,
    ): void {
        if ($previousStatus === $newStatus && ! $reason) {
            return;
        }

        PostModerationLog::query()->create([
            'post_id' => $post->id,
            'moderator_id' => $moderator?->id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    private function ensureAuthorSubscription(Post $post, User $author): void
    {
        SceneSubscription::query()->firstOrCreate([
            'scene_id' => $post->scene_id,
            'user_id' => $author->id,
        ], [
            'is_muted' => false,
            'last_read_post_id' => $post->id,
            'last_read_at' => now(),
        ]);
    }
}
