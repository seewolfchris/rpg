<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignInvitationController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiceRollController;
use App\Http\Controllers\GmModerationController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SceneBookmarkController;
use App\Http\Controllers\SceneController;
use App\Http\Controllers\SceneSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('/wissen', 'knowledge.index')
    ->name('knowledge.index');

Route::view('/wissen/wie-spielt-man', 'knowledge.how-to-play')
    ->name('knowledge.how-to-play');

Route::view('/wissen/regelwerk', 'knowledge.rules')
    ->name('knowledge.rules');

Route::view('/wissen/enzyklopaedie', 'knowledge.encyclopedia')
    ->name('knowledge.encyclopedia');

Route::redirect('/hilfe', '/wissen', 301)
    ->name('help.index');

Route::middleware('guest')->group(function () {
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

Route::middleware('auth')->scopeBindings()->group(function () {
    Route::get('/dashboard', DashboardController::class)
        ->name('dashboard');
    Route::get('/leaderboard', [LeaderboardController::class, 'index'])
        ->name('leaderboard.index');

    Route::resource('characters', CharacterController::class);
    Route::resource('campaigns', CampaignController::class);
    Route::get('/campaign-invitations', [CampaignInvitationController::class, 'index'])
        ->name('campaign-invitations.index');
    Route::patch('/campaign-invitations/{invitation}/accept', [CampaignInvitationController::class, 'accept'])
        ->name('campaign-invitations.accept');
    Route::patch('/campaign-invitations/{invitation}/decline', [CampaignInvitationController::class, 'decline'])
        ->name('campaign-invitations.decline');
    Route::post('/campaigns/{campaign}/invitations', [CampaignInvitationController::class, 'store'])
        ->name('campaigns.invitations.store');
    Route::delete('/campaigns/{campaign}/invitations/{invitation}', [CampaignInvitationController::class, 'destroy'])
        ->name('campaigns.invitations.destroy');
    Route::resource('campaigns.scenes', SceneController::class)->except('index');
    Route::get('/scene-subscriptions', [SceneSubscriptionController::class, 'index'])
        ->name('scene-subscriptions.index');
    Route::get('/bookmarks', [SceneBookmarkController::class, 'index'])
        ->name('bookmarks.index');
    Route::patch('/scene-subscriptions/bulk', [SceneSubscriptionController::class, 'bulkUpdate'])
        ->name('scene-subscriptions.bulk-update');
    Route::post('/campaigns/{campaign}/scenes/{scene}/subscribe', [SceneSubscriptionController::class, 'subscribe'])
        ->name('campaigns.scenes.subscribe');
    Route::delete('/campaigns/{campaign}/scenes/{scene}/subscribe', [SceneSubscriptionController::class, 'unsubscribe'])
        ->name('campaigns.scenes.unsubscribe');
    Route::patch('/campaigns/{campaign}/scenes/{scene}/subscription/mute', [SceneSubscriptionController::class, 'toggleMute'])
        ->name('campaigns.scenes.subscription.mute');
    Route::patch('/campaigns/{campaign}/scenes/{scene}/subscription/read', [SceneSubscriptionController::class, 'markRead'])
        ->name('campaigns.scenes.subscription.read');
    Route::patch('/campaigns/{campaign}/scenes/{scene}/subscription/unread', [SceneSubscriptionController::class, 'markUnread'])
        ->name('campaigns.scenes.subscription.unread');
    Route::post('/campaigns/{campaign}/scenes/{scene}/bookmark', [SceneBookmarkController::class, 'store'])
        ->name('campaigns.scenes.bookmark.store');
    Route::delete('/campaigns/{campaign}/scenes/{scene}/bookmark', [SceneBookmarkController::class, 'destroy'])
        ->name('campaigns.scenes.bookmark.destroy');
    Route::get('/notifications/preferences', [NotificationController::class, 'preferences'])
        ->name('notifications.preferences');
    Route::patch('/notifications/preferences', [NotificationController::class, 'updatePreferences'])
        ->name('notifications.preferences.update');
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('notifications.read-all');
    Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'read'])
        ->name('notifications.read');

    Route::post('/campaigns/{campaign}/scenes/{scene}/posts', [PostController::class, 'store'])
        ->middleware('throttle:posts')
        ->name('campaigns.scenes.posts.store');

    Route::post('/campaigns/{campaign}/scenes/{scene}/dice-rolls', [DiceRollController::class, 'store'])
        ->middleware('throttle:dice-rolls')
        ->name('campaigns.scenes.dice-rolls.store');

    Route::get('/posts/{post}/edit', [PostController::class, 'edit'])
        ->name('posts.edit');

    Route::patch('/posts/{post}', [PostController::class, 'update'])
        ->middleware('throttle:posts')
        ->name('posts.update');

    Route::delete('/posts/{post}', [PostController::class, 'destroy'])
        ->name('posts.destroy');

    Route::patch('/posts/{post}/moderate', [PostController::class, 'moderate'])
        ->name('posts.moderate');
    Route::patch('/posts/{post}/pin', [PostController::class, 'pin'])
        ->name('posts.pin');
    Route::patch('/posts/{post}/unpin', [PostController::class, 'unpin'])
        ->name('posts.unpin');

    Route::get('/gm/moderation', [GmModerationController::class, 'index'])
        ->middleware('role:gm,admin')
        ->name('gm.moderation.index');
    Route::patch('/gm/moderation/bulk', [GmModerationController::class, 'bulkUpdate'])
        ->middleware('role:gm,admin')
        ->name('gm.moderation.bulk-update');

    Route::view('/gm', 'gm.index')
        ->middleware('role:gm,admin')
        ->name('gm.index');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
