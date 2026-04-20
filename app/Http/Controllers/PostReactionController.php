<?php

namespace App\Http\Controllers;

use App\Actions\Post\CreatePostReactionAction;
use App\Actions\Post\DeletePostReactionAction;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\Post\PostReactionRequest;
use App\Models\Post;
use App\Models\World;
use App\Support\SensitiveFeatureGate;
use Illuminate\Http\RedirectResponse;

class PostReactionController extends Controller
{
    use EnsuresWorldContext;

    public function __construct(
        private readonly CreatePostReactionAction $createPostReactionAction,
        private readonly DeletePostReactionAction $deletePostReactionAction,
    ) {}

    public function store(PostReactionRequest $request, World $world, Post $post): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        abort_unless(SensitiveFeatureGate::enabled('features.wave4.reactions', false), 404);

        $post->loadMissing(Post::WORLD_CONTEXT_RELATIONS);
        $this->ensurePostBelongsToWorld($world, $post);
        $this->authorize('view', $post->scene);

        $data = $request->validated();

        $this->createPostReactionAction->execute(
            world: $world,
            post: $post,
            reactor: $user,
            emoji: (string) $data['emoji'],
        );

        return back();
    }

    public function destroy(PostReactionRequest $request, World $world, Post $post): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        abort_unless(SensitiveFeatureGate::enabled('features.wave4.reactions', false), 404);

        $post->loadMissing(Post::WORLD_CONTEXT_RELATIONS);
        $this->ensurePostBelongsToWorld($world, $post);
        $this->authorize('view', $post->scene);

        $data = $request->validated();

        $this->deletePostReactionAction->execute(
            world: $world,
            post: $post,
            reactor: $user,
            emoji: (string) $data['emoji'],
        );

        return back();
    }
}
