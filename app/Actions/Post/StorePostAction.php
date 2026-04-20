<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Domain\Post\StorePostResult;
use App\Domain\Post\StorePostService;
use App\Models\Scene;
use App\Models\User;

final class StorePostAction
{
    public function __construct(
        private readonly StorePostService $storePostService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Scene $scene, User $author, array $data): StorePostResult
    {
        return $this->storePostService->store(
            scene: $scene,
            user: $author,
            data: $data,
        );
    }
}
