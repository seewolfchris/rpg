<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\SceneBookmark\StoreSceneBookmarkRequest;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneBookmark;
use App\Models\User;
use App\Models\World;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SceneBookmarkController extends Controller
{
    use EnsuresWorldContext;

    public function index(Request $request, World $world): View
    {
        $user = $this->requireAuthenticatedUser($request);
        $search = trim((string) $request->query('q', ''));

        $bookmarksQuery = SceneBookmark::query()
            ->where('user_id', $user->id)
            ->whereHas('scene.campaign', function (Builder $campaignQuery) use ($user, $world): void {
                $campaignQuery->whereIn('id', Campaign::query()
                    ->visibleTo($user)
                    ->forWorld($world)
                    ->select('id'));
            })
            ->with(['scene.campaign', 'post'])
            ->latest('updated_at');

        if ($search !== '') {
            $searchTerm = '%'.$search.'%';
            $bookmarksQuery->where(function (Builder $query) use ($searchTerm): void {
                $query->where('label', 'like', $searchTerm)
                    ->orWhereHas('scene', function (Builder $sceneQuery) use ($searchTerm): void {
                        $sceneQuery->where('title', 'like', $searchTerm);
                    })
                    ->orWhereHas('scene.campaign', function (Builder $campaignQuery) use ($searchTerm): void {
                        $campaignQuery->where('title', 'like', $searchTerm);
                    });
            });
        }

        /** @var LengthAwarePaginator<SceneBookmark> $bookmarks */
        $bookmarks = $bookmarksQuery
            ->paginate(20)
            ->withQueryString();

        $bookmarkJumpUrls = [];
        foreach ($bookmarks as $bookmark) {
            $bookmarkJumpUrls[$bookmark->id] = $this->buildBookmarkUrl($bookmark);
        }

        $totalCount = SceneBookmark::query()
            ->where('user_id', $user->id)
            ->whereHas('scene.campaign', function (Builder $campaignQuery) use ($user, $world): void {
                $campaignQuery->whereIn('id', Campaign::query()
                    ->visibleTo($user)
                    ->forWorld($world)
                    ->select('id'));
            })
            ->count();

        return view('bookmarks.index', compact('world', 'bookmarks', 'bookmarkJumpUrls', 'search', 'totalCount'));
    }

    public function store(
        StoreSceneBookmarkRequest $request,
        World $world,
        Campaign $campaign,
        Scene $scene,
    ): View|RedirectResponse {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('view', $scene);

        $data = $request->validated();

        $postId = isset($data['post_id']) ? (int) $data['post_id'] : 0;

        if ($postId > 0) {
            $postBelongsToScene = Post::query()
                ->where('scene_id', $scene->id)
                ->whereKey($postId)
                ->exists();

            if (! $postBelongsToScene) {
                throw ValidationException::withMessages([
                    'post_id' => 'Der gewählte Post gehört nicht zu dieser Szene.',
                ]);
            }
        } else {
            $latestPostId = (int) Post::query()
                ->where('scene_id', $scene->id)
                ->max('id');
            $postId = $latestPostId > 0 ? $latestPostId : 0;
        }

        SceneBookmark::query()->updateOrCreate([
            'user_id' => $request->user()->id,
            'scene_id' => $scene->id,
        ], [
            'post_id' => $postId > 0 ? $postId : null,
            'label' => $this->normalizeLabel((string) ($data['label'] ?? '')),
        ]);

        if ($request->header('HX-Request') === 'true' && $postId > 0) {
            $post = Post::query()
                ->where('scene_id', $scene->id)
                ->whereKey($postId)
                ->with(Post::THREAD_ITEM_RELATIONS)
                ->first();

            if ($post instanceof Post) {
                $bookmarkCountForNav = $this->visibleBookmarkCountForUser($request->user());

                return view('posts._thread-item', compact('post', 'scene', 'campaign', 'bookmarkCountForNav'));
            }
        }

        return back()->with('status', 'Bookmark gespeichert.');
    }

    public function destroy(Request $request, World $world, Campaign $campaign, Scene $scene): RedirectResponse
    {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('view', $scene);

        SceneBookmark::query()
            ->where('user_id', $request->user()->id)
            ->where('scene_id', $scene->id)
            ->delete();

        return back()->with('status', 'Bookmark entfernt.');
    }

    private function normalizeLabel(string $label): ?string
    {
        $normalized = trim($label);

        return $normalized !== '' ? $normalized : null;
    }

    private function buildBookmarkUrl(SceneBookmark $bookmark): string
    {
        $scene = $bookmark->scene;

        if (! $scene instanceof Scene) {
            return route('bookmarks.index');
        }

        $campaign = $scene->campaign;

        if (! $campaign instanceof Campaign) {
            return route('bookmarks.index');
        }

        $world = $campaign->world;

        $postId = (int) ($bookmark->post_id ?? 0);

        if ($postId <= 0) {
            return route('campaigns.scenes.show', [
                'world' => $world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]);
        }

        $postExistsInScene = Post::query()
            ->where('scene_id', $scene->id)
            ->whereKey($postId)
            ->exists();

        if (! $postExistsInScene) {
            return route('campaigns.scenes.show', [
                'world' => $world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]);
        }

        $newerPostsCount = (int) Post::query()
            ->where('scene_id', $scene->id)
            ->where('id', '>', $postId)
            ->count();

        $page = intdiv($newerPostsCount, Post::THREAD_POSTS_PER_PAGE) + 1;

        return route('campaigns.scenes.show', [
            'world' => $world,
            'campaign' => $campaign,
            'scene' => $scene,
            'page' => $page,
        ]).'#post-'.$postId;
    }

    private function requireAuthenticatedUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException();
        }

        return $user;
    }

    private function visibleBookmarkCountForUser(?\App\Models\User $user): int
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
