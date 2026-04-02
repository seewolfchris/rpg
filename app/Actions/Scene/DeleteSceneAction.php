<?php

declare(strict_types=1);

namespace App\Actions\Scene;

use App\Domain\Scene\SceneHeaderImageStorage;
use App\Models\Scene;
use Illuminate\Support\Facades\DB;

class DeleteSceneAction
{
    public function __construct(
        private readonly SceneHeaderImageStorage $sceneHeaderImageStorage,
    ) {}

    public function execute(Scene $scene): void
    {
        $headerImagePath = is_string($scene->header_image_path) && $scene->header_image_path !== ''
            ? $scene->header_image_path
            : null;

        DB::transaction(function () use ($scene, $headerImagePath): void {
            $scene->delete();

            if ($headerImagePath !== null) {
                DB::afterCommit(function () use ($headerImagePath): void {
                    $this->sceneHeaderImageStorage->delete($headerImagePath);
                });
            }
        });
    }
}
