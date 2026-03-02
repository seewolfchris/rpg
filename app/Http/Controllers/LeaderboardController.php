<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
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

        return view('leaderboard.index', [
            'leaders' => $leaders,
            'rank' => $rank,
        ]);
    }
}
