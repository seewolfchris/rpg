<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\SceneBookmark;
use App\Models\SceneSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $topPlayers = User::query()
            ->select(['id', 'name', 'points'])
            ->where('points', '>', 0)
            ->orderByDesc('points')
            ->orderBy('name')
            ->limit(5)
            ->get();

        $pendingModerationCount = auth()->user()->isGmOrAdmin()
            ? Post::query()->where('moderation_status', 'pending')->count()
            : 0;

        $latestPostsPerScene = Post::query()
            ->selectRaw('scene_id, MAX(id) as latest_post_id')
            ->groupBy('scene_id');

        $unreadSceneCount = (int) SceneSubscription::query()
            ->whereHas('scene.campaign', fn (Builder $campaignQuery) => $campaignQuery->visibleTo(auth()->user()))
            ->leftJoinSub($latestPostsPerScene, 'latest_posts', function ($join): void {
                $join->on('scene_subscriptions.scene_id', '=', 'latest_posts.scene_id');
            })
            ->where('scene_subscriptions.user_id', auth()->id())
            ->whereNotNull('latest_posts.latest_post_id')
            ->where(function ($query): void {
                $query->whereNull('scene_subscriptions.last_read_post_id')
                    ->orWhereColumn('scene_subscriptions.last_read_post_id', '<', 'latest_posts.latest_post_id');
            })
            ->count();

        $bookmarkCount = (int) SceneBookmark::query()
            ->where('user_id', auth()->id())
            ->whereHas('scene.campaign', fn (Builder $campaignQuery) => $campaignQuery->visibleTo(auth()->user()))
            ->count();

        return view('dashboard', [
            'topPlayers' => $topPlayers,
            'pendingModerationCount' => $pendingModerationCount,
            'unreadSceneCount' => $unreadSceneCount,
            'bookmarkCount' => $bookmarkCount,
        ]);
    }
}
