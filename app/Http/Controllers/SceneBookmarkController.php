<?php

namespace App\Http\Controllers;

use App\Http\Requests\SceneBookmark\StoreSceneBookmarkRequest;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneBookmark;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SceneBookmarkController extends Controller
{
    private const THREAD_POSTS_PER_PAGE = 20;

    public function index(Request $request): View
    {
        $user = $request->user();
        $search = trim((string) $request->query('q', ''));

        $bookmarksQuery = SceneBookmark::query()
            ->where('user_id', $user->id)
            ->whereHas('scene.campaign', fn (Builder $campaignQuery) => $campaignQuery->visibleTo($user))
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
            ->whereHas('scene.campaign', fn (Builder $campaignQuery) => $campaignQuery->visibleTo($user))
            ->count();

        return view('bookmarks.index', compact('bookmarks', 'bookmarkJumpUrls', 'search', 'totalCount'));
    }

    public function store(
        StoreSceneBookmarkRequest $request,
        Campaign $campaign,
        Scene $scene,
    ): RedirectResponse {
        $this->ensureSceneBelongsToCampaign($campaign, $scene);
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

        return back()->with('status', 'Bookmark gespeichert.');
    }

    public function destroy(Request $request, Campaign $campaign, Scene $scene): RedirectResponse
    {
        $this->ensureSceneBelongsToCampaign($campaign, $scene);
        $this->authorize('view', $scene);

        SceneBookmark::query()
            ->where('user_id', $request->user()->id)
            ->where('scene_id', $scene->id)
            ->delete();

        return back()->with('status', 'Bookmark entfernt.');
    }

    private function ensureSceneBelongsToCampaign(Campaign $campaign, Scene $scene): void
    {
        abort_unless($scene->campaign_id === $campaign->id, 404);
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

        $postId = (int) ($bookmark->post_id ?? 0);

        if ($postId <= 0) {
            return route('campaigns.scenes.show', [$scene->campaign, $scene]);
        }

        $postExistsInScene = Post::query()
            ->where('scene_id', $scene->id)
            ->whereKey($postId)
            ->exists();

        if (! $postExistsInScene) {
            return route('campaigns.scenes.show', [$scene->campaign, $scene]);
        }

        $newerPostsCount = (int) Post::query()
            ->where('scene_id', $scene->id)
            ->where('id', '>', $postId)
            ->count();

        $page = intdiv($newerPostsCount, self::THREAD_POSTS_PER_PAGE) + 1;

        return route('campaigns.scenes.show', [
            'campaign' => $scene->campaign,
            'scene' => $scene,
            'page' => $page,
        ]).'#post-'.$postId;
    }
}
