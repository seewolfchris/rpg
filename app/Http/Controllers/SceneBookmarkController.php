<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\SceneBookmark\StoreSceneBookmarkRequest;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneBookmark;
use App\Models\World;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SceneBookmarkController extends Controller
{
    use EnsuresWorldContext;

    public function index(Request $request, World $world): View
    {
        $user = $request->user();
        $search = trim((string) $request->query('q', ''));

        $bookmarksQuery = SceneBookmark::query()
            ->where('user_id', $user->id)
            ->whereHas('scene.campaign', fn (Builder $campaignQuery) => $campaignQuery
                ->visibleTo($user)
                ->forWorld($world))
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

        $bookmarks = $bookmarksQuery
            ->paginate(20)
            ->withQueryString();

        $bookmarkJumpUrls = [];
        foreach ($bookmarks as $bookmark) {
            $bookmarkJumpUrls[$bookmark->id] = $this->buildBookmarkUrl($bookmark);
        }

        $totalCount = SceneBookmark::query()
            ->where('user_id', $user->id)
            ->whereHas('scene.campaign', fn (Builder $campaignQuery) => $campaignQuery
                ->visibleTo($user)
                ->forWorld($world))
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
                return view('posts._thread-item', compact('post', 'scene', 'campaign'));
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

        if (! $scene || ! $scene->campaign) {
            return route('bookmarks.index');
        }

        $world = $scene->campaign->world;

        $postId = (int) ($bookmark->post_id ?? 0);

        if ($postId <= 0) {
            return route('campaigns.scenes.show', [
                'world' => $world,
                'campaign' => $scene->campaign,
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
                'campaign' => $scene->campaign,
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
            'campaign' => $scene->campaign,
            'scene' => $scene,
            'page' => $page,
        ]).'#post-'.$postId;
    }
}
