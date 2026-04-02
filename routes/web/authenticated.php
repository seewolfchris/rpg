<?php

use App\Http\Controllers\AdminUserModerationController;
use App\Http\Controllers\Api\WebPushSubscriptionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CampaignInvitationController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\CharacterProgressionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\WorldCallingOptionAdminController;
use App\Http\Controllers\WorldAdminController;
use App\Http\Controllers\WorldCharacterOptionTemplateAdminController;
use App\Http\Controllers\WorldSpeciesOptionAdminController;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\World;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/** @var callable(Request):string $resolveWorldSlug */
$resolveWorldSlug = (isset($resolveWorldSlug) && is_callable($resolveWorldSlug))
    ? $resolveWorldSlug
    : static function (Request $request): string {
        $sessionSlug = $request->session()->get('world_slug');

        if (is_string($sessionSlug) && $sessionSlug !== '') {
            return $sessionSlug;
        }

        return World::defaultSlug();
    };

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

    Route::patch('/characters/{character}/inline', [CharacterController::class, 'inlineUpdate'])
        ->middleware('throttle:writes')
        ->name('characters.inline-update');

    Route::get('/campaign-invitations', [CampaignInvitationController::class, 'index'])
        ->name('campaign-invitations.index');

    Route::patch('/campaign-invitations/{invitation}/accept', [CampaignInvitationController::class, 'acceptLegacy'])
        ->middleware('throttle:writes')
        ->name('campaign-invitations.accept.legacy');

    Route::patch('/campaign-invitations/{invitation}/decline', [CampaignInvitationController::class, 'declineLegacy'])
        ->middleware('throttle:writes')
        ->name('campaign-invitations.decline.legacy');

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

    Route::get('/gm', function (Request $request) {
        $user = $request->user();

        abort_unless(
            $user && ($user->isGmOrAdmin() || $user->hasAnyCoGmCampaignAccess()),
            403
        );

        return view('gm.index');
    })->name('gm.index');

    Route::prefix('/admin')
        ->middleware('role:admin')
        ->group(function (): void {
            Route::get('users/moderation', [AdminUserModerationController::class, 'index'])
                ->name('admin.users.moderation.index');

            Route::patch('users/{user}/moderation', [AdminUserModerationController::class, 'update'])
                ->name('admin.users.moderation.update')
                ->middleware('throttle:moderation');

            Route::patch('worlds/{world}/toggle-active', [WorldAdminController::class, 'toggleActive'])
                ->name('admin.worlds.toggle-active')
                ->middleware('throttle:moderation');

            Route::patch('worlds/{world}/move/{direction}', [WorldAdminController::class, 'move'])
                ->where('direction', 'up|down')
                ->name('admin.worlds.move')
                ->middleware('throttle:moderation');

            Route::post('worlds/{world}/character-options/import-template', [WorldCharacterOptionTemplateAdminController::class, 'importTemplate'])
                ->name('admin.worlds.character-options.import-template')
                ->middleware('throttle:moderation');

            Route::post('worlds/{world}/species-options', [WorldSpeciesOptionAdminController::class, 'store'])
                ->name('admin.worlds.species-options.store')
                ->middleware('throttle:moderation');

            Route::patch('worlds/{world}/species-options/{speciesOption}', [WorldSpeciesOptionAdminController::class, 'update'])
                ->name('admin.worlds.species-options.update')
                ->middleware('throttle:moderation');

            Route::patch('worlds/{world}/species-options/{speciesOption}/toggle', [WorldSpeciesOptionAdminController::class, 'toggle'])
                ->name('admin.worlds.species-options.toggle')
                ->middleware('throttle:moderation');

            Route::patch('worlds/{world}/species-options/{speciesOption}/move/{direction}', [WorldSpeciesOptionAdminController::class, 'move'])
                ->where('direction', 'up|down')
                ->name('admin.worlds.species-options.move')
                ->middleware('throttle:moderation');

            Route::post('worlds/{world}/calling-options', [WorldCallingOptionAdminController::class, 'store'])
                ->name('admin.worlds.calling-options.store')
                ->middleware('throttle:moderation');

            Route::patch('worlds/{world}/calling-options/{callingOption}', [WorldCallingOptionAdminController::class, 'update'])
                ->name('admin.worlds.calling-options.update')
                ->middleware('throttle:moderation');

            Route::patch('worlds/{world}/calling-options/{callingOption}/toggle', [WorldCallingOptionAdminController::class, 'toggle'])
                ->name('admin.worlds.calling-options.toggle')
                ->middleware('throttle:moderation');

            Route::patch('worlds/{world}/calling-options/{callingOption}/move/{direction}', [WorldCallingOptionAdminController::class, 'move'])
                ->where('direction', 'up|down')
                ->name('admin.worlds.calling-options.move')
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
    });
});
