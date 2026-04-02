<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\Post\PostReactionRequest;
use App\Models\Post;
use App\Models\PostReaction;
use App\Models\World;
use Illuminate\Http\RedirectResponse;

class PostReactionController extends Controller
{
    use EnsuresWorldContext;

    public function store(PostReactionRequest $request, World $world, Post $post): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        abort_unless((bool) config('features.wave4.reactions', false), 404);

        $post->loadMissing(Post::WORLD_CONTEXT_RELATIONS);
        $this->ensurePostBelongsToWorld($world, $post);
        $this->authorize('view', $post->scene);

        $data = $request->validated();

        PostReaction::query()->upsert([
            [
                'post_id' => $post->id,
                'user_id' => $user->id,
                'emoji' => (string) $data['emoji'],
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], [
            'post_id',
            'user_id',
            'emoji',
        ], [
            'updated_at',
        ]);

        return back();
    }

    public function destroy(PostReactionRequest $request, World $world, Post $post): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        abort_unless((bool) config('features.wave4.reactions', false), 404);

        $post->loadMissing(Post::WORLD_CONTEXT_RELATIONS);
        $this->ensurePostBelongsToWorld($world, $post);
        $this->authorize('view', $post->scene);

        $data = $request->validated();

        PostReaction::query()
            ->where('post_id', $post->id)
            ->where('user_id', $user->id)
            ->where('emoji', (string) $data['emoji'])
            ->delete();

        return back();
    }
}
