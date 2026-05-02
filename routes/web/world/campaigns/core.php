<?php

use App\Http\Controllers\CampaignGmContactThreadController;
use App\Http\Controllers\CampaignInvitationController;
use App\Http\Controllers\CampaignMembershipController;
use App\Http\Controllers\SceneController;
use Illuminate\Support\Facades\Route;

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

Route::get('/campaigns/{campaign}/scenes/{scene}/thread', [SceneController::class, 'threadPage'])
    ->name('campaigns.scenes.thread');
