<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Models\Post;

class AttachCombatPhaseResultMetaAction
{
    /**
     * @param  array<string, mixed>  $combatPhaseResult
     */
    public function execute(Post $post, array $combatPhaseResult): void
    {
        $meta = is_array($post->meta) ? $post->meta : [];
        $meta['combat_phase_result'] = $combatPhaseResult;

        $post->forceFill(['meta' => $meta]);
        $post->save();
    }
}
