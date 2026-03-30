<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Models\Post;
use App\Models\PostReaction;
use App\Models\World;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PostReactionController extends Controller
{
    use EnsuresWorldContext;

    public function store(Request $request, World $world, Post $post): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        abort_unless((bool) config('features.wave4.reactions', false), 404);

        $post->loadMissing(Post::WORLD_CONTEXT_RELATIONS);
        $this->ensurePostBelongsToWorld($world, $post);
        $this->authorize('view', $post->scene);

        $data = $request->validate([
            'emoji' => ['required', 'string', Rule::in(PostReaction::ALLOWED_EMOJIS)],
        ]);

        PostReaction::query()->firstOrCreate([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'emoji' => (string) $data['emoji'],
        ]);

        return back();
    }

    public function destroy(Request $request, World $world, Post $post): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        abort_unless((bool) config('features.wave4.reactions', false), 404);

        $post->loadMissing(Post::WORLD_CONTEXT_RELATIONS);
        $this->ensurePostBelongsToWorld($world, $post);
        $this->authorize('view', $post->scene);

        $data = $request->validate([
            'emoji' => ['required', 'string', Rule::in(PostReaction::ALLOWED_EMOJIS)],
        ]);

        PostReaction::query()
            ->where('post_id', $post->id)
            ->where('user_id', $user->id)
            ->where('emoji', (string) $data['emoji'])
            ->delete();

        return back();
    }
}
