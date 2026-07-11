<?php

declare(strict_types=1);

namespace App\Domain\Scene;

use App\Models\Campaign;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ScenePostVisibility
{
    public function canViewUnapprovedPosts(Scene $scene, User $user): bool
    {
        $scene->loadMissing('campaign');
        $campaign = $scene->campaign;

        return $campaign instanceof Campaign
            && $campaign->canModeratePosts($user);
    }

    /**
     * @param  Builder<\App\Models\Post>  $query
     * @return Builder<\App\Models\Post>
     */
    public function apply(Builder $query, Scene $scene, User $user, string $table = 'posts'): Builder
    {
        if ($this->canViewUnapprovedPosts($scene, $user)) {
            return $query;
        }

        return $query->where(static function (Builder $visibilityQuery) use ($table, $user): void {
            $visibilityQuery
                ->where($table.'.moderation_status', 'approved')
                ->orWhere($table.'.user_id', (int) $user->id);
        });
    }
}
