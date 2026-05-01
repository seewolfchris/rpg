<?php

namespace App\Domain\Scene;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\World;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ScenePostAnchorUrlService
{
    public function __construct(
        private readonly int $threadPostsPerPage = 20,
    ) {}

    /**
     * @param  array<int, int>  $postIds
     * @return array<int, string>
     */
    public function build(World $world, Campaign $campaign, Scene $scene, array $postIds): array
    {
        $normalizedIds = array_values(array_unique(array_map(
            static fn ($postId): int => (int) $postId,
            array_filter($postIds, static fn ($postId): bool => (int) $postId > 0)
        )));

        if ($normalizedIds === []) {
            return [];
        }

        $olderPostCounts = Post::query()
            ->from('posts as current_posts')
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->selectRaw('current_posts.id as post_id')
            ->selectRaw(<<<'SQL'
                (
                    SELECT COUNT(*)
                    FROM posts as older_posts
                    WHERE older_posts.scene_id = current_posts.scene_id
                        AND (
                            older_posts.created_at < current_posts.created_at
                            OR (
                                older_posts.created_at = current_posts.created_at
                                AND older_posts.id < current_posts.id
                            )
                        )
                ) as older_posts_count
            SQL)
            ->where('current_posts.scene_id', $scene->id)
            ->whereIn('current_posts.id', $normalizedIds)
            ->pluck('older_posts_count', 'post_id');

        $urls = [];

        foreach ($olderPostCounts as $postId => $olderPostsCount) {
            $postId = (int) $postId;
            $page = intdiv((int) $olderPostsCount, $this->threadPostsPerPage) + 1;

            $urls[$postId] = route('campaigns.scenes.show', [
                'world' => $world,
                'campaign' => $campaign,
                'scene' => $scene,
                'page' => $page,
            ]).'#post-'.$postId;
        }

        return $urls;
    }
}
