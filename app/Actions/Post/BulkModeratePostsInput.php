<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Models\User;
use App\Models\World;
use Illuminate\Support\Collection;

final readonly class BulkModeratePostsInput
{
    /**
     * @param  Collection<int, int<1, max>>  $postIds
     */
    public function __construct(
        public World $world,
        public User $moderator,
        public string $statusFilter,
        public string $search,
        public string $targetStatus,
        public ?string $moderationNote,
        public int $sceneId,
        public Collection $postIds,
        public bool $isHtmxRequest,
    ) {}
}
