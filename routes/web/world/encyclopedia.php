<?php

use App\Http\Controllers\EncyclopediaWorkflowController;
use Illuminate\Support\Facades\Route;

Route::get('/wissen/enzyklopaedie/vorschlagen', [EncyclopediaWorkflowController::class, 'createProposal'])
    ->name('knowledge.encyclopedia.proposals.create');

Route::post('/wissen/enzyklopaedie/vorschlagen', [EncyclopediaWorkflowController::class, 'storeProposal'])
    ->middleware('throttle:writes')
    ->name('knowledge.encyclopedia.proposals.store');

Route::get('/wissen/enzyklopaedie/vorschlagen/{encyclopediaEntry}/bearbeiten', [EncyclopediaWorkflowController::class, 'editProposal'])
    ->withoutScopedBindings()
    ->name('knowledge.encyclopedia.proposals.edit');

Route::put('/wissen/enzyklopaedie/vorschlagen/{encyclopediaEntry}/bearbeiten', [EncyclopediaWorkflowController::class, 'updateProposal'])
    ->withoutScopedBindings()
    ->middleware('throttle:writes')
    ->name('knowledge.encyclopedia.proposals.update');

Route::get('/wissen/enzyklopaedie/moderation', [EncyclopediaWorkflowController::class, 'moderationIndex'])
    ->name('knowledge.encyclopedia.moderation.index');

Route::patch('/wissen/enzyklopaedie/moderation/{encyclopediaEntry}/freigeben', [EncyclopediaWorkflowController::class, 'approve'])
    ->withoutScopedBindings()
    ->middleware('throttle:moderation')
    ->name('knowledge.encyclopedia.moderation.approve');

Route::patch('/wissen/enzyklopaedie/moderation/{encyclopediaEntry}/ablehnen', [EncyclopediaWorkflowController::class, 'reject'])
    ->withoutScopedBindings()
    ->middleware('throttle:moderation')
    ->name('knowledge.encyclopedia.moderation.reject');
