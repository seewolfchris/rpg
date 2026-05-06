<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Models\Post;

class AttachMagicResultMetaAction
{
    /**
     * @param  array<string, mixed>  $magicResult
     */
    public function execute(Post $post, array $magicResult): void
    {
        $meta = is_array($post->meta) ? $post->meta : [];
        $meta['magic_result'] = $magicResult;

        $post->forceFill(['meta' => $meta]);
        $post->save();
    }
}
