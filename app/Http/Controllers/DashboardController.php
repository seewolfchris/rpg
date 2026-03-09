<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\DiceRoll;
use App\Models\Post;
use App\Models\SceneSubscription;
use App\Models\User;
use App\Models\World;
use App\Support\NavigationCounters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly NavigationCounters $navigationCounters,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = auth()->user();
        $worlds = World::query()
            ->active()
            ->ordered()
            ->get(['id', 'name', 'slug']);

        $selectedWorldSlug = trim((string) $request->session()->get('world_slug', World::defaultSlug()));
        $selectedWorld = $worlds->firstWhere('slug', $selectedWorldSlug) ?? $worlds->first();

        if ($selectedWorld) {
            $request->session()->put('world_slug', $selectedWorld->slug);
        }

        $topPlayers = User::query()
            ->select(['id', 'name', 'points'])
            ->where('points', '>', 0)
            ->orderByDesc('points')
            ->orderBy('name')
            ->limit(5)
            ->get();

        $pendingModerationCount = $user->isGmOrAdmin()
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

        $bookmarkCount = $this->navigationCounters->forUser($user)['bookmarkCount'];

        $hasCharacter = Character::query()
            ->where('user_id', $user->id)
            ->exists();

        $hasSceneSubscription = SceneSubscription::query()
            ->where('user_id', $user->id)
            ->whereHas('scene.campaign', fn (Builder $campaignQuery) => $campaignQuery->visibleTo($user))
            ->exists();

        $hasPost = Post::query()
            ->where('user_id', $user->id)
            ->exists();

        $hasDiceRoll = DiceRoll::query()
            ->where(function (Builder $query) use ($user): void {
                $query
                    ->where('user_id', $user->id)
                    ->orWhereHas('character', fn (Builder $characterQuery) => $characterQuery->where('user_id', $user->id));
            })
            ->exists();

        $tutorialSteps = [
            [
                'title' => 'Charakter anlegen',
                'description' => 'Erstelle deine Figur mit Eigenschaften, Biografie und Bild.',
                'done' => $hasCharacter,
                'url' => route('characters.create', ['world' => $selectedWorld?->slug]),
                'cta' => $hasCharacter ? 'Bearbeiten' : 'Jetzt erstellen',
            ],
            [
                'title' => 'Szene abonnieren',
                'description' => 'Abonniere eine Szene, um Updates und ungelesene Posts zu sehen.',
                'done' => $hasSceneSubscription,
                'url' => route('scene-subscriptions.index', ['world' => $selectedWorld]),
                'cta' => $hasSceneSubscription ? 'Abos ansehen' : 'Abo setzen',
            ],
            [
                'title' => 'Ersten IC/OOC-Post schreiben',
                'description' => 'IC bitte in Ich-Perspektive verfassen, als spricht dein Held selbst.',
                'done' => $hasPost,
                'url' => route('campaigns.index', ['world' => $selectedWorld]),
                'cta' => $hasPost ? 'Weiter schreiben' : 'Jetzt posten',
            ],
            [
                'title' => 'Erste GM-Probe im Thread',
                'description' => 'GM/Co-GM führt Proben mit Anlass, Ziel-Held und Modifikator direkt im Post aus.',
                'done' => $hasDiceRoll,
                'url' => route('campaigns.index', ['world' => $selectedWorld]),
                'cta' => $hasDiceRoll ? 'Probe im Thread ansehen' : 'GM-Probe verfolgen',
            ],
            [
                'title' => 'Erstes Bookmark setzen',
                'description' => 'Markiere wichtige Szenenstellen für schnellen Wiedereinstieg.',
                'done' => $bookmarkCount > 0,
                'url' => route('bookmarks.index', ['world' => $selectedWorld]),
                'cta' => $bookmarkCount > 0 ? 'Bookmarks ansehen' : 'Bookmark setzen',
            ],
        ];

        $tutorialCompletedCount = collect($tutorialSteps)
            ->filter(fn (array $step): bool => (bool) $step['done'])
            ->count();

        return view('dashboard', [
            'topPlayers' => $topPlayers,
            'pendingModerationCount' => $pendingModerationCount,
            'unreadSceneCount' => $unreadSceneCount,
            'bookmarkCount' => $bookmarkCount,
            'tutorialSteps' => $tutorialSteps,
            'tutorialCompletedCount' => $tutorialCompletedCount,
            'worlds' => $worlds,
            'selectedWorld' => $selectedWorld,
        ]);
    }
}
