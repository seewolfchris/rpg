<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    public function index(Request $request): View
    {
        $leaders = User::query()
            ->select(['id', 'name', 'role', 'points'])
            ->withCount([
                'pointEvents as approved_posts_count' => fn ($query) => $query
                    ->where('source_type', 'post')
                    ->where('event_key', 'approved'),
                'characters',
            ])
            ->where('points', '>', 0)
            ->orderByDesc('points')
            ->orderBy('name')
            ->limit(100)
            ->get();

        $user = $request->user();

        $rank = User::query()
            ->where(function ($query) use ($user): void {
                $query
                    ->where('points', '>', (int) $user->points)
                    ->orWhere(function ($innerQuery) use ($user): void {
                        $innerQuery
                            ->where('points', (int) $user->points)
                            ->where('id', '<', $user->id);
                    });
            })
            ->count() + 1;

        $activeCharactersThisWeek = collect();

        if ((bool) config('features.wave4.active_characters_week', false)) {
            $activeCharactersThisWeek = Character::query()
                ->select(['characters.id', 'characters.name', 'characters.user_id'])
                ->selectRaw('COUNT(posts.id) as weekly_posts_count')
                ->join('posts', 'posts.character_id', '=', 'characters.id')
                ->where('posts.post_type', 'ic')
                ->where('posts.created_at', '>=', Carbon::now()->subDays(7))
                ->groupBy('characters.id', 'characters.name', 'characters.user_id')
                ->with('user:id,name')
                ->orderByDesc('weekly_posts_count')
                ->orderBy('characters.name')
                ->limit(10)
                ->get();
        }

        return view('leaderboard.index', [
            'leaders' => $leaders,
            'rank' => $rank,
            'activeCharactersThisWeek' => $activeCharactersThisWeek,
        ]);
    }
}
