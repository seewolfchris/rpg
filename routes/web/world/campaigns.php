<?php

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignGmContactThreadController;
use App\Http\Controllers\CampaignInvitationController;
use App\Http\Controllers\CampaignMembershipController;
use App\Http\Controllers\HandoutController;
use App\Http\Controllers\SceneBookmarkController;
use App\Http\Controllers\SceneController;
use App\Http\Controllers\SceneSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::resource('campaigns', CampaignController::class)
    ->middlewareFor(['store', 'update', 'destroy'], 'throttle:writes');

Route::post('/campaigns/{campaign}/gm-contacts', [CampaignGmContactThreadController::class, 'store'])
    ->middleware('throttle:writes')
    ->name('campaigns.gm-contacts.store');

Route::get('/campaigns/{campaign}/gm-contacts/{gmContactThread}', [CampaignGmContactThreadController::class, 'show'])
    ->name('campaigns.gm-contacts.show');

Route::post('/campaigns/{campaign}/gm-contacts/{gmContactThread}/messages', [CampaignGmContactThreadController::class, 'storeMessage'])
    ->middleware('throttle:writes')
    ->name('campaigns.gm-contacts.messages.store');

Route::patch('/campaigns/{campaign}/gm-contacts/{gmContactThread}/status', [CampaignGmContactThreadController::class, 'updateStatus'])
    ->middleware('throttle:writes')
    ->name('campaigns.gm-contacts.status.update');

Route::post('/campaigns/{campaign}/invitations', [CampaignInvitationController::class, 'store'])
    ->middleware('throttle:writes')
    ->name('campaigns.invitations.store');

Route::delete('/campaigns/{campaign}/invitations/{invitation}', [CampaignInvitationController::class, 'destroy'])
    ->middleware('throttle:writes')
    ->name('campaigns.invitations.destroy');

Route::patch('/campaigns/{campaign}/memberships/{membership}', [CampaignMembershipController::class, 'update'])
    ->middleware('throttle:writes')
    ->name('campaigns.memberships.update');

Route::patch('/campaign-invitations/{invitation}/accept', [CampaignInvitationController::class, 'accept'])
    ->withoutScopedBindings()
    ->middleware('throttle:writes')
    ->name('campaign-invitations.accept');

Route::patch('/campaign-invitations/{invitation}/decline', [CampaignInvitationController::class, 'decline'])
    ->withoutScopedBindings()
    ->middleware('throttle:writes')
    ->name('campaign-invitations.decline');

Route::resource('campaigns.scenes', SceneController::class)
    ->except('index')
    ->middlewareFor(['store', 'update', 'destroy'], 'throttle:writes');

Route::get('/campaigns/{campaign}/handouts', [HandoutController::class, 'index'])
    ->name('campaigns.handouts.index');

Route::get('/campaigns/{campaign}/handouts/create', [HandoutController::class, 'create'])
    ->name('campaigns.handouts.create');

Route::post('/campaigns/{campaign}/handouts', [HandoutController::class, 'store'])
    ->middleware('throttle:writes')
    ->name('campaigns.handouts.store');

Route::get('/campaigns/{campaign}/handouts/{handout}', [HandoutController::class, 'show'])
    ->name('campaigns.handouts.show');

Route::get('/campaigns/{campaign}/handouts/{handout}/edit', [HandoutController::class, 'edit'])
    ->name('campaigns.handouts.edit');

Route::patch('/campaigns/{campaign}/handouts/{handout}', [HandoutController::class, 'update'])
    ->middleware('throttle:writes')
    ->name('campaigns.handouts.update');

Route::delete('/campaigns/{campaign}/handouts/{handout}', [HandoutController::class, 'destroy'])
    ->middleware('throttle:writes')
    ->name('campaigns.handouts.destroy');

Route::patch('/campaigns/{campaign}/handouts/{handout}/reveal', [HandoutController::class, 'reveal'])
    ->middleware('throttle:writes')
    ->name('campaigns.handouts.reveal');

Route::patch('/campaigns/{campaign}/handouts/{handout}/unreveal', [HandoutController::class, 'unreveal'])
    ->middleware('throttle:writes')
    ->name('campaigns.handouts.unreveal');

Route::get('/campaigns/{campaign}/handouts/{handout}/file', [HandoutController::class, 'file'])
    ->name('campaigns.handouts.file');

Route::get('/campaigns/{campaign}/scenes/{scene}/thread', [SceneController::class, 'threadPage'])
    ->name('campaigns.scenes.thread');

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
