<?php

declare(strict_types=1);

namespace App\Actions\Scene;

use App\Domain\Scene\SceneHeaderImageStorage;
use App\Models\Campaign;
use App\Models\Scene;
use App\Models\SceneSubscription;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class StoreSceneAction
{
    public function __construct(
        private readonly SceneHeaderImageStorage $sceneHeaderImageStorage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(
        Campaign $campaign,
        array $data,
        int $creatorId,
        ?UploadedFile $headerImage = null,
    ): Scene {
        unset($data['header_image'], $data['remove_header_image']);

        $data['campaign_id'] = $campaign->id;
        $data['created_by'] = $creatorId > 0 ? $creatorId : null;
        $data['header_image_path'] = null;
        $stagedHeaderImage = $this->sceneHeaderImageStorage->stage($headerImage);

        try {
            return DB::transaction(function () use ($campaign, $data, $creatorId, $stagedHeaderImage): Scene {
                /** @var Scene $scene */
                $scene = Scene::query()->create($data);

                $this->ensureDefaultSubscriptions($scene, $creatorId, (int) $campaign->owner_id);

                if ($stagedHeaderImage !== null) {
                    DB::afterCommit(function () use ($scene, $stagedHeaderImage): void {
                        $this->sceneHeaderImageStorage->finalize($scene, $stagedHeaderImage, null);
                    });
                }

                return $scene;
            });
        } catch (Throwable $exception) {
            $this->sceneHeaderImageStorage->discard($stagedHeaderImage);

            throw $exception;
        }
    }

    private function ensureDefaultSubscriptions(Scene $scene, int $creatorId, int $ownerId): void
    {
        $userIds = collect([$creatorId, $ownerId])
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        foreach ($userIds as $userId) {
            SceneSubscription::query()->firstOrCreate([
                'scene_id' => $scene->id,
                'user_id' => $userId,
            ], [
                'is_muted' => false,
                'last_read_post_id' => null,
                'last_read_at' => now(),
            ]);
        }
    }
}
