<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post\BulkModerationRequest;
use App\Models\Post;
use App\Models\PostModerationLog;
use App\Models\User;
use App\Notifications\PostModerationStatusNotification;
use App\Support\Gamification\PointService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GmModerationController extends Controller
{
    public function __construct(
        private readonly PointService $pointService,
    ) {}

    public function index(Request $request): View
    {
        $status = in_array((string) $request->query('status', 'pending'), ['all', 'pending', 'approved', 'rejected'], true)
            ? (string) $request->query('status', 'pending')
            : 'pending';
        $search = trim((string) $request->query('q', ''));

        $baseQuery = Post::query()->whereHas('scene.campaign');

        $postsQuery = (clone $baseQuery)
            ->with(['scene.campaign', 'scene', 'user', 'character', 'approvedBy', 'latestModerationLog.moderator'])
            ->withCount('moderationLogs');

        $this->applyFilters($postsQuery, $status, $search);

        if ($status === 'all') {
            $postsQuery->orderByRaw("CASE moderation_status WHEN 'pending' THEN 0 WHEN 'rejected' THEN 1 ELSE 2 END");
        }

        $posts = $postsQuery
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        $totalCount = (clone $baseQuery)->count();
        $pendingCount = (clone $baseQuery)->where('moderation_status', 'pending')->count();
        $approvedCount = (clone $baseQuery)->where('moderation_status', 'approved')->count();
        $rejectedCount = (clone $baseQuery)->where('moderation_status', 'rejected')->count();

        return view('gm.moderation', compact(
            'posts',
            'status',
            'search',
            'totalCount',
            'pendingCount',
            'approvedCount',
            'rejectedCount',
        ));
    }

    public function bulkUpdate(BulkModerationRequest $request): RedirectResponse
    {
        $statusFilter = (string) $request->validated('status', 'pending');
        $search = trim((string) $request->validated('q', ''));
        $targetStatus = (string) $request->validated('moderation_status');
        $moderationNote = $this->normalizeModerationNote((string) $request->validated('moderation_note', ''));
        $moderator = $request->user();

        $postsQuery = Post::query()
            ->whereHas('scene.campaign')
            ->with(['scene.campaign', 'user']);

        $this->applyFilters($postsQuery, $statusFilter, $search);

        $posts = $postsQuery->get();
        $affected = 0;

        foreach ($posts as $post) {
            $previousStatus = (string) $post->moderation_status;

            if ($previousStatus === $targetStatus && ! $moderationNote) {
                continue;
            }

            $post->moderation_status = $targetStatus;

            if ($targetStatus === 'approved') {
                $post->approved_at = now();
                $post->approved_by = $moderator->id;
            } else {
                $post->approved_at = null;
                $post->approved_by = null;
            }

            $post->save();

            PostModerationLog::query()->create([
                'post_id' => $post->id,
                'moderator_id' => $moderator->id,
                'previous_status' => $previousStatus,
                'new_status' => $targetStatus,
                'reason' => $moderationNote,
                'created_at' => now(),
            ]);

            $this->pointService->syncApprovedPost($post);
            $this->notifyAuthorAboutBulkModeration(
                post: $post,
                moderator: $moderator,
                previousStatus: $previousStatus,
                newStatus: $targetStatus,
                moderationNote: $moderationNote,
            );

            $affected++;
        }

        return redirect()
            ->route('gm.moderation.index', [
                'status' => $statusFilter,
                'q' => $search !== '' ? $search : null,
            ])
            ->with('status', 'Bulk-Moderation ausgefuehrt. Betroffene Posts: '.$affected.'.');
    }

    private function applyFilters(Builder $query, string $status, string $search): void
    {
        if ($status !== 'all') {
            $query->where('moderation_status', $status);
        }

        if ($search !== '') {
            $searchTerm = '%'.$search.'%';
            $query->where(function (Builder $innerQuery) use ($searchTerm, $search): void {
                $innerQuery->where('content', 'like', $searchTerm)
                    ->orWhereHas('user', function (Builder $userQuery) use ($searchTerm): void {
                        $userQuery->where('name', 'like', $searchTerm);
                    })
                    ->orWhereHas('scene', function (Builder $sceneQuery) use ($searchTerm): void {
                        $sceneQuery->where('title', 'like', $searchTerm);
                    })
                    ->orWhereHas('scene.campaign', function (Builder $campaignQuery) use ($searchTerm): void {
                        $campaignQuery->where('title', 'like', $searchTerm);
                    })
                    ->orWhereHas('latestModerationLog', function (Builder $logQuery) use ($searchTerm): void {
                        $logQuery->where('reason', 'like', $searchTerm);
                    });

                if (is_numeric($search)) {
                    $innerQuery->orWhere('id', (int) $search);
                }
            });
        }
    }

    private function normalizeModerationNote(string $note): ?string
    {
        $normalized = trim($note);

        return $normalized !== '' ? $normalized : null;
    }

    private function notifyAuthorAboutBulkModeration(
        Post $post,
        User $moderator,
        string $previousStatus,
        string $newStatus,
        ?string $moderationNote,
    ): void {
        if ($post->user_id === $moderator->id) {
            return;
        }

        if ($previousStatus === $newStatus && ! $moderationNote) {
            return;
        }

        $post->user->notify(new PostModerationStatusNotification(
            post: $post,
            moderator: $moderator,
            previousStatus: $previousStatus,
            newStatus: $newStatus,
            moderationNote: $moderationNote,
        ));
    }
}
