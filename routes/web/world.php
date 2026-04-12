<?php

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignInvitationController;
use App\Http\Controllers\EncyclopediaCategoryController;
use App\Http\Controllers\EncyclopediaEntryController;
use App\Http\Controllers\GmModerationController;
use App\Http\Controllers\GmProgressionController;
use App\Http\Controllers\KnowledgeEncyclopediaController;
use App\Http\Controllers\KnowledgePageController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PostReactionController;
use App\Http\Controllers\SceneBookmarkController;
use App\Http\Controllers\SceneController;
use App\Http\Controllers\SceneSubscriptionController;
use App\Http\Controllers\WorldController;
use Illuminate\Support\Facades\Route;

Route::prefix('/w/{world:slug}')->scopeBindings()->group(function (): void {
    Route::get('/', [WorldController::class, 'show'])
        ->name('worlds.show');

    Route::get('/wissen', [KnowledgePageController::class, 'index'])
        ->name('knowledge.index');

    Route::get('/wissen/wie-spielt-man', [KnowledgePageController::class, 'howToPlay'])
        ->name('knowledge.how-to-play');

    Route::get('/wissen/regelwerk', [KnowledgePageController::class, 'rules'])
        ->name('knowledge.rules');

    Route::get('/wissen/weltueberblick', [KnowledgePageController::class, 'worldOverview'])
        ->name('knowledge.world-overview');

    Route::get('/wissen/lore/{category?}', [KnowledgePageController::class, 'worldLore'])
        ->where('category', '[a-z0-9\\-]+')
        ->name('knowledge.lore');

    Route::get('/wissen/enzyklopaedie', [KnowledgeEncyclopediaController::class, 'encyclopedia'])
        ->name('knowledge.encyclopedia');

    Route::get('/wissen/enzyklopaedie/{categorySlug}/{entrySlug}', [KnowledgeEncyclopediaController::class, 'encyclopediaEntry'])
        ->where([
            'categorySlug' => '(?!admin(?:/|$))[a-z0-9\\-]+',
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

        Route::post('/posts/preview', [PostController::class, 'preview'])
            ->middleware('throttle:writes')
            ->name('posts.preview');

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

        Route::post('/posts/{post}/reactions', [PostReactionController::class, 'store'])
            ->withoutScopedBindings()
            ->middleware('throttle:writes')
            ->name('posts.reactions.store');

        Route::delete('/posts/{post}/reactions', [PostReactionController::class, 'destroy'])
            ->withoutScopedBindings()
            ->middleware('throttle:writes')
            ->name('posts.reactions.destroy');

        Route::get('/gm/moderation', [GmModerationController::class, 'index'])
            ->name('gm.moderation.index');

        Route::patch('/gm/moderation/bulk', [GmModerationController::class, 'bulkUpdate'])
            ->middleware('throttle:moderation')
            ->name('gm.moderation.bulk-update');

        Route::post('/gm/moderation/{post}/probe', [GmModerationController::class, 'probe'])
            ->withoutScopedBindings()
            ->middleware('throttle:moderation')
            ->name('gm.moderation.probe');

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
