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
        $user = $this->authenticatedUser($request);
        $search = trim((string) $request->query('q', ''));

        $bookmarksQuery = SceneBookmark::query()
            ->where('user_id', $user->id)
            ->whereHas('scene.campaign', function (Builder $campaignQuery) use ($user, $world): void {
                $campaignQuery->whereIn('id', Campaign::query()
                    ->visibleTo($user)
                    ->forWorld($world)
                    ->select('id'));
            })
            ->with('scene.campaign.world')
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

        /** @var LengthAwarePaginator<int, SceneBookmark> $bookmarks */
        $bookmarks = $bookmarksQuery
            ->paginate(20)
            ->withQueryString();

        $bookmarkJumpUrls = $this->buildBookmarkUrls($bookmarks, $world);

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
        $user = $this->authenticatedUser($request);

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
            'user_id' => $user->id,
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
                $bookmarkCountForNav = $this->visibleBookmarkCountForUser($user);

                return view('posts._thread-item', compact('post', 'scene', 'campaign', 'bookmarkCountForNav'));
            }
        }

        return back()->with('status', 'Bookmark gespeichert.');
    }

    public function destroy(Request $request, World $world, Campaign $campaign, Scene $scene): RedirectResponse
    {
        $this->ensureSceneBelongsToWorld($world, $campaign, $scene);
        $this->authorize('view', $scene);
        $user = $this->authenticatedUser($request);

        SceneBookmark::query()
            ->where('user_id', $user->id)
            ->where('scene_id', $scene->id)
            ->delete();

        return back()->with('status', 'Bookmark entfernt.');
    }

    private function normalizeLabel(string $label): ?string
    {
        $normalized = trim($label);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  LengthAwarePaginator<int, SceneBookmark>  $bookmarks
     * @return array<int, string>
     */
    private function buildBookmarkUrls(LengthAwarePaginator $bookmarks, World $world): array
    {
        $bookmarkJumpUrls = [];
        $sceneIdByPostId = [];

        foreach ($bookmarks as $bookmark) {
            $bookmarkId = (int) $bookmark->id;
            $scene = $bookmark->scene;
            $campaign = $scene?->campaign;

            if (! $scene instanceof Scene || ! $campaign instanceof Campaign) {
                $bookmarkJumpUrls[$bookmarkId] = route('bookmarks.index', ['world' => $world]);

                continue;
            }

            $bookmarkJumpUrls[$bookmarkId] = $this->sceneRouteUrl($world, $campaign, $scene);

            $postId = (int) ($bookmark->post_id ?? 0);
            if ($postId > 0) {
                $sceneIdByPostId[$postId] = (int) $scene->id;
            }
        }

        if ($sceneIdByPostId === []) {
            return $bookmarkJumpUrls;
        }

        $postRows = Post::query()
            ->from('posts as current_posts')
            ->selectRaw('current_posts.id as post_id')
            ->selectRaw('current_posts.scene_id as scene_id')
            ->selectRaw('(SELECT COUNT(*) FROM posts as newer_posts WHERE newer_posts.scene_id = current_posts.scene_id AND newer_posts.id > current_posts.id) as newer_posts_count')
            ->whereIn('current_posts.id', array_keys($sceneIdByPostId))
            ->get();

        $newerPostsCountByPostId = [];
        foreach ($postRows as $postRow) {
            $postId = (int) ($postRow->post_id ?? 0);
            $sceneId = (int) ($postRow->scene_id ?? 0);
            if ($postId <= 0 || ($sceneIdByPostId[$postId] ?? 0) !== $sceneId) {
                continue;
            }

            $newerPostsCountByPostId[$postId] = (int) ($postRow->newer_posts_count ?? 0);
        }

        foreach ($bookmarks as $bookmark) {
            $scene = $bookmark->scene;
            $campaign = $scene?->campaign;
            $postId = (int) ($bookmark->post_id ?? 0);
            if ($postId <= 0 || ! $scene instanceof Scene || ! $campaign instanceof Campaign) {
                continue;
            }

            if (! array_key_exists($postId, $newerPostsCountByPostId)) {
                continue;
            }

            $page = intdiv($newerPostsCountByPostId[$postId], Post::THREAD_POSTS_PER_PAGE) + 1;
            $bookmarkJumpUrls[(int) $bookmark->id] = $this->sceneRouteUrl($world, $campaign, $scene, $page).'#post-'.$postId;
        }

        return $bookmarkJumpUrls;
    }

    private function sceneRouteUrl(World $world, Campaign $campaign, Scene $scene, ?int $page = null): string
    {
        $parameters = [
            'world' => $world,
            'campaign' => $campaign,
            'scene' => $scene,
        ];

        if ($page !== null) {
            $parameters['page'] = $page;
        }

        return route('campaigns.scenes.show', $parameters);
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
