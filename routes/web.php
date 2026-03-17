<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Api\WebPushSubscriptionController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignInvitationController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\CharacterProgressionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EncyclopediaCategoryController;
use App\Http\Controllers\EncyclopediaEntryController;
use App\Http\Controllers\GmProgressionController;
use App\Http\Controllers\GmModerationController;
use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SceneBookmarkController;
use App\Http\Controllers\SceneController;
use App\Http\Controllers\SceneSubscriptionController;
use App\Http\Controllers\WorldAdminController;
use App\Http\Controllers\WorldController;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\World;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

$resolveWorldSlug = static function (Request $request): string {
    $sessionSlug = $request->session()->get('world_slug');

    if (is_string($sessionSlug) && $sessionSlug !== '') {
        return $sessionSlug;
    }

    return World::defaultSlug();
};

Route::get('/', function () {
    $worlds = collect();

    if (Schema::hasTable('worlds')) {
        $worlds = World::query()
            ->active()
            ->ordered()
            ->withCount('campaigns')
            ->get();
    }

    return view('welcome', compact('worlds'));
})->name('home');

Route::get('/welten', [WorldController::class, 'index'])
    ->name('worlds.index');

Route::post('/welten/{world:slug}/aktivieren', [WorldController::class, 'activate'])
    ->middleware('throttle:writes')
    ->name('worlds.activate');

Route::get('/wissen', [KnowledgeController::class, 'index'])
    ->name('knowledge.global.index');

Route::get('/wissen/wie-spielt-man', [KnowledgeController::class, 'howToPlay'])
    ->name('knowledge.global.how-to-play');

Route::get('/wissen/regelwerk', [KnowledgeController::class, 'rules'])
    ->name('knowledge.global.rules');

Route::get('/wissen/enzyklopaedie', [KnowledgeController::class, 'encyclopedia'])
    ->name('knowledge.global.encyclopedia');

Route::prefix('/w/{world:slug}')->scopeBindings()->group(function (): void {
    Route::get('/', [WorldController::class, 'show'])
        ->name('worlds.show');

    Route::get('/wissen', [KnowledgeController::class, 'index'])
        ->name('knowledge.index');

    Route::get('/wissen/wie-spielt-man', [KnowledgeController::class, 'howToPlay'])
        ->name('knowledge.how-to-play');

    Route::get('/wissen/regelwerk', [KnowledgeController::class, 'rules'])
        ->name('knowledge.rules');

    Route::get('/wissen/enzyklopaedie', [KnowledgeController::class, 'encyclopedia'])
        ->name('knowledge.encyclopedia');

    Route::get('/wissen/enzyklopaedie/{categorySlug}/{entrySlug}', [KnowledgeController::class, 'encyclopediaEntry'])
        ->where([
            'categorySlug' => '(?!admin$)[a-z0-9\\-]+',
            'entrySlug' => '[a-z0-9\\-]+',
        ])
        ->name('knowledge.encyclopedia.entry');

    Route::middleware('auth')->scopeBindings()->group(function (): void {
        Route::resource('campaigns', CampaignController::class)
            ->middlewareFor(['store', 'update', 'destroy'], 'throttle:writes');

        Route::post('/campaigns/{campaign}/invitations', [CampaignInvitationController::class, 'store'])
            ->middleware('throttle:writes')
            ->name('campaigns.invitations.store');

        Route::delete('/campaigns/{campaign}/invitations/{invitation}', [CampaignInvitationController::class, 'destroy'])
            ->middleware('throttle:writes')
            ->name('campaigns.invitations.destroy');

        Route::resource('campaigns.scenes', SceneController::class)
            ->except('index')
            ->middlewareFor(['store', 'update', 'destroy'], 'throttle:writes');

        Route::get('/scene-subscriptions', [SceneSubscriptionController::class, 'index'])
            ->name('scene-subscriptions.index');

        Route::patch('/scene-subscriptions/bulk', [SceneSubscriptionController::class, 'bulkUpdate'])
            ->middleware('throttle:writes')
            ->name('scene-subscriptions.bulk-update');

        Route::get('/bookmarks', [SceneBookmarkController::class, 'index'])
            ->name('bookmarks.index');

        Route::post('/campaigns/{campaign}/scenes/{scene}/subscribe', [SceneSubscriptionController::class, 'subscribe'])
            ->middleware('throttle:writes')
            ->name('campaigns.scenes.subscribe');

        Route::delete('/campaigns/{campaign}/scenes/{scene}/subscribe', [SceneSubscriptionController::class, 'unsubscribe'])
            ->middleware('throttle:writes')
            ->name('campaigns.scenes.unsubscribe');

        Route::patch('/campaigns/{campaign}/scenes/{scene}/subscription/mute', [SceneSubscriptionController::class, 'toggleMute'])
            ->middleware('throttle:writes')
            ->name('campaigns.scenes.subscription.mute');

        Route::patch('/campaigns/{campaign}/scenes/{scene}/subscription/read', [SceneSubscriptionController::class, 'markRead'])
            ->middleware('throttle:writes')
            ->name('campaigns.scenes.subscription.read');

        Route::patch('/campaigns/{campaign}/scenes/{scene}/subscription/unread', [SceneSubscriptionController::class, 'markUnread'])
            ->middleware('throttle:writes')
            ->name('campaigns.scenes.subscription.unread');

        Route::post('/campaigns/{campaign}/scenes/{scene}/bookmark', [SceneBookmarkController::class, 'store'])
            ->middleware('throttle:writes')
            ->name('campaigns.scenes.bookmark.store');

        Route::delete('/campaigns/{campaign}/scenes/{scene}/bookmark', [SceneBookmarkController::class, 'destroy'])
            ->middleware('throttle:writes')
            ->name('campaigns.scenes.bookmark.destroy');

        Route::post('/campaigns/{campaign}/scenes/{scene}/posts', [PostController::class, 'store'])
            ->middleware('throttle:writes')
            ->name('campaigns.scenes.posts.store');

        Route::post('/campaigns/{campaign}/scenes/{scene}/inventory-quick-action', [SceneController::class, 'inventoryQuickAction'])
            ->middleware('throttle:writes')
            ->name('campaigns.scenes.inventory-quick-action');

        Route::get('/posts/{post}/edit', [PostController::class, 'edit'])
            ->withoutScopedBindings()
            ->name('posts.edit');

        Route::patch('/posts/{post}', [PostController::class, 'update'])
            ->withoutScopedBindings()
            ->middleware('throttle:writes')
            ->name('posts.update');

        Route::delete('/posts/{post}', [PostController::class, 'destroy'])
            ->withoutScopedBindings()
            ->middleware('throttle:writes')
            ->name('posts.destroy');

        Route::patch('/posts/{post}/moderate', [PostController::class, 'moderate'])
            ->withoutScopedBindings()
            ->middleware('throttle:moderation')
            ->name('posts.moderate');

        Route::patch('/posts/{post}/pin', [PostController::class, 'pin'])
            ->withoutScopedBindings()
            ->middleware('throttle:moderation')
            ->name('posts.pin');

        Route::patch('/posts/{post}/unpin', [PostController::class, 'unpin'])
            ->withoutScopedBindings()
            ->middleware('throttle:moderation')
            ->name('posts.unpin');

        Route::get('/gm/moderation', [GmModerationController::class, 'index'])
            ->middleware('role:gm,admin')
            ->name('gm.moderation.index');

        Route::patch('/gm/moderation/bulk', [GmModerationController::class, 'bulkUpdate'])
            ->middleware(['role:gm,admin', 'throttle:moderation'])
            ->name('gm.moderation.bulk-update');

        Route::get('/gm/progression', [GmProgressionController::class, 'index'])
            ->name('gm.progression.index');

        Route::post('/gm/progression/xp', [GmProgressionController::class, 'awardXp'])
            ->middleware('throttle:moderation')
            ->name('gm.progression.award-xp');

        Route::prefix('/wissen/enzyklopaedie/admin')
            ->as('knowledge.admin.')
            ->middleware('role:gm,admin')
            ->group(function (): void {
                Route::resource('kategorien', EncyclopediaCategoryController::class)
                    ->parameters(['kategorien' => 'encyclopediaCategory'])
                    ->except(['show'])
                    ->middlewareFor(['store', 'update', 'destroy'], 'throttle:moderation');

                Route::resource('kategorien.eintraege', EncyclopediaEntryController::class)
                    ->parameters([
                        'kategorien' => 'encyclopediaCategory',
                        'eintraege' => 'encyclopediaEntry',
                    ])
                    ->except(['show', 'index'])
                    ->middlewareFor(['store', 'update', 'destroy'], 'throttle:moderation');
            });
    });
});

Route::get('/wissen/enzyklopaedie/{categorySlug}/{entrySlug}', function (
    Request $request,
    string $categorySlug,
    string $entrySlug
) {
    $sessionSlug = $request->session()->get('world_slug');

    if (is_string($sessionSlug) && $sessionSlug !== '') {
        return redirect()->route('knowledge.encyclopedia.entry', [
            'world' => $sessionSlug,
            'categorySlug' => $categorySlug,
            'entrySlug' => $entrySlug,
        ], 302);
    }

    return redirect()->route('knowledge.global.encyclopedia', [], 302);
})->where([
    'categorySlug' => '(?!admin$)[a-z0-9\\-]+',
    'entrySlug' => '[a-z0-9\\-]+',
]);

Route::get('/hilfe', function () {
    return redirect()->route('knowledge.global.index', [], 302);
})->name('help.index');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:register');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:password-update')
        ->name('password.update');
});

Route::middleware('auth')->scopeBindings()->group(function () use ($resolveWorldSlug): void {
    Route::get('/dashboard', DashboardController::class)
        ->name('dashboard');

    Route::get('/leaderboard', [LeaderboardController::class, 'index'])
        ->name('leaderboard.index');

    Route::resource('characters', CharacterController::class)
        ->middlewareFor(['store', 'update', 'destroy'], 'throttle:writes');

    Route::post('/characters/{character}/progression/spend', [CharacterProgressionController::class, 'spend'])
        ->middleware('throttle:writes')
        ->name('characters.progression.spend');

    Route::get('/campaign-invitations', [CampaignInvitationController::class, 'index'])
        ->name('campaign-invitations.index');

    Route::patch('/campaign-invitations/{invitation}/accept', [CampaignInvitationController::class, 'accept'])
        ->middleware('throttle:writes')
        ->name('campaign-invitations.accept');

    Route::patch('/campaign-invitations/{invitation}/decline', [CampaignInvitationController::class, 'decline'])
        ->middleware('throttle:writes')
        ->name('campaign-invitations.decline');

    Route::get('/notifications/preferences', [NotificationController::class, 'preferences'])
        ->name('notifications.preferences');

    Route::patch('/notifications/preferences', [NotificationController::class, 'updatePreferences'])
        ->middleware('throttle:notifications')
        ->name('notifications.preferences.update');

    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');

    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])
        ->middleware('throttle:notifications')
        ->name('notifications.read-all');

    Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'read'])
        ->middleware('throttle:notifications')
        ->name('notifications.read');

    Route::post('/api/webpush/subscribe', [WebPushSubscriptionController::class, 'subscribe'])
        ->middleware('throttle:webpush-subscriptions')
        ->name('api.webpush.subscribe');

    Route::post('/api/webpush/unsubscribe', [WebPushSubscriptionController::class, 'unsubscribe'])
        ->middleware('throttle:webpush-subscriptions')
        ->name('api.webpush.unsubscribe');

    Route::view('/gm', 'gm.index')
        ->middleware('role:gm,admin')
        ->name('gm.index');

    Route::prefix('/admin')
        ->middleware('role:admin')
        ->group(function (): void {
            Route::patch('worlds/{world}/toggle-active', [WorldAdminController::class, 'toggleActive'])
                ->name('admin.worlds.toggle-active')
                ->middleware('throttle:moderation');

            Route::patch('worlds/{world}/move/{direction}', [WorldAdminController::class, 'move'])
                ->where('direction', 'up|down')
                ->name('admin.worlds.move')
                ->middleware('throttle:moderation');

            Route::resource('worlds', WorldAdminController::class)
                ->except(['show'])
                ->names('admin.worlds')
                ->middlewareFor(['store', 'update', 'destroy'], 'throttle:moderation');
        });

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware('throttle:writes')
        ->name('logout');

    Route::get('/campaigns', function (Request $request) use ($resolveWorldSlug) {
        return redirect()->route('campaigns.index', ['world' => $resolveWorldSlug($request)], 301);
    });

    Route::get('/campaigns/create', function (Request $request) use ($resolveWorldSlug) {
        return redirect()->route('campaigns.create', ['world' => $resolveWorldSlug($request)], 301);
    });

    Route::get('/campaigns/{campaign}', function (Campaign $campaign) {
        return redirect()->route('campaigns.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ], 301);
    });

    Route::get('/campaigns/{campaign}/edit', function (Campaign $campaign) {
        return redirect()->route('campaigns.edit', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ], 301);
    });

    Route::get('/campaigns/{campaign}/scenes/create', function (Campaign $campaign) {
        return redirect()->route('campaigns.scenes.create', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ], 301);
    });

    Route::get('/campaigns/{campaign}/scenes/{scene}', function (Campaign $campaign, Scene $scene) {
        abort_unless($scene->campaign_id === $campaign->id, 404);

        return redirect()->route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ], 301);
    });

    Route::get('/campaigns/{campaign}/scenes/{scene}/edit', function (Campaign $campaign, Scene $scene) {
        abort_unless($scene->campaign_id === $campaign->id, 404);

        return redirect()->route('campaigns.scenes.edit', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ], 301);
    });

    Route::get('/scene-subscriptions', function (Request $request) use ($resolveWorldSlug) {
        return redirect()->route('scene-subscriptions.index', ['world' => $resolveWorldSlug($request)], 301);
    });

    Route::get('/bookmarks', function (Request $request) use ($resolveWorldSlug) {
        return redirect()->route('bookmarks.index', ['world' => $resolveWorldSlug($request)], 301);
    });

    Route::get('/posts/{post}/edit', function (Post $post) {
        /** @var Scene $scene */
        $scene = $post->scene;
        /** @var Campaign $campaign */
        $campaign = $scene->campaign;

        return redirect()->route('posts.edit', [
            'world' => $campaign->world,
            'post' => $post,
        ], 301);
    });

    Route::get('/gm/moderation', function (Request $request) use ($resolveWorldSlug) {
        return redirect()->route('gm.moderation.index', ['world' => $resolveWorldSlug($request)], 301);
    })->middleware('role:gm,admin');
});
