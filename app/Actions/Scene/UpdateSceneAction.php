<?php

declare(strict_types=1);

namespace App\Actions\Scene;

use App\Domain\Scene\SceneHeaderImageStorage;
use App\Models\Scene;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class UpdateSceneAction
{
    public function __construct(
        private readonly SceneHeaderImageStorage $sceneHeaderImageStorage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(
        Scene $scene,
        array $data,
        ?UploadedFile $headerImage = null,
        bool $removeHeaderImage = false,
    ): void {
        unset($data['header_image'], $data['remove_header_image']);

        $stagedHeaderImage = $this->sceneHeaderImageStorage->stage($headerImage);
        $replaceHeaderImage = $stagedHeaderImage !== null;
        $previousHeaderPath = is_string($scene->header_image_path) && $scene->header_image_path !== ''
            ? $scene->header_image_path
            : null;

        try {
            DB::transaction(function () use (
                $scene,
                $data,
                $replaceHeaderImage,
                $removeHeaderImage,
                $previousHeaderPath,
                $stagedHeaderImage
            ): void {
                if ($removeHeaderImage && ! $replaceHeaderImage) {
                    $data['header_image_path'] = null;
                }

                $scene->update($data);

                if ($replaceHeaderImage && $stagedHeaderImage !== null) {
                    DB::afterCommit(function () use ($scene, $stagedHeaderImage, $previousHeaderPath): void {
                        $this->sceneHeaderImageStorage->finalize($scene, $stagedHeaderImage, $previousHeaderPath);
                    });

                    return;
                }

                if ($removeHeaderImage && $previousHeaderPath !== null) {
                    DB::afterCommit(function () use ($previousHeaderPath): void {
                        $this->sceneHeaderImageStorage->delete($previousHeaderPath);
                    });
                }
            });
        } catch (Throwable $exception) {
            $this->sceneHeaderImageStorage->discard($stagedHeaderImage);

            throw $exception;
        }
    }
}
