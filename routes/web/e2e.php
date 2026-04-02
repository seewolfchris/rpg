<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('/_e2e')->group(function (): void {
    Route::get('/offline-queue', function () {
        return view('e2e.offline-queue');
    })->name('e2e.offline-queue');

    Route::post('/offline-queue/submit', function (Request $request): JsonResponse {
        $validated = $request->validate([
            'content' => ['required', 'string', 'min:3'],
            'content_format' => ['required', 'string'],
            'post_type' => ['required', 'string'],
        ]);

        $request->session()->put('e2e.offline_queue.last_submission', $validated['content']);

        return response()->json([
            'status' => 'ok',
            'content' => $validated['content'],
        ]);
    })->name('e2e.offline-queue.submit');

    Route::get('/offline-queue/status', function (Request $request): JsonResponse {
        return response()->json([
            'last_submission' => (string) $request->session()->get('e2e.offline_queue.last_submission', ''),
        ]);
    })->name('e2e.offline-queue.status');
});
