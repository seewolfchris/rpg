<?php

declare(strict_types=1);

namespace App\Actions\Campaign;

use App\Domain\Scene\SceneHeaderImageStorage;
use App\Models\Campaign;
use App\Models\Handout;
use App\Models\Post;
use App\Models\Scene;
use App\Models\World;
use Illuminate\Database\DatabaseManager;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class DeleteCampaignAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly SceneHeaderImageStorage $sceneHeaderImageStorage,
    ) {}

    public function execute(World $world, Campaign $campaign): void
    {
        $this->db->transaction(function () use ($world, $campaign): void {
            $lockedCampaign = $this->lockAndVerifyContext($world, $campaign);

            $this->persistDeletion($lockedCampaign);
        }, 3);
    }

    private function lockAndVerifyContext(World $world, Campaign $campaign): Campaign
    {
        /** @var World $lockedWorld */
        $lockedWorld = World::query()
            ->whereKey((int) $world->id)
            ->lockForUpdate()
            ->firstOrFail();

        /** @var Campaign $lockedCampaign */
        $lockedCampaign = Campaign::query()
            ->whereKey((int) $campaign->id)
            ->where('world_id', (int) $lockedWorld->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedCampaign;
    }

    private function persistDeletion(Campaign $campaign): void
    {
        $this->cleanupCampaignMedia($campaign);

        $campaign->delete();
    }

    private function cleanupCampaignMedia(Campaign $campaign): void
    {
        $campaignId = (int) $campaign->id;

        $sceneHeaderPaths = Scene::query()
            ->where('campaign_id', $campaignId)
            ->whereNotNull('header_image_path')
            ->orderBy('id')
            ->pluck('header_image_path')
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->values();

        $handoutMediaIds = Media::query()
            ->join('handouts', 'media.model_id', '=', 'handouts.id')
            ->where('media.model_type', Handout::class)
            ->where('media.collection_name', Handout::HANDOUT_FILE_COLLECTION)
            ->where('handouts.campaign_id', $campaignId)
            ->orderBy('media.id')
            ->pluck('media.id');

        $postMediaIds = Media::query()
            ->join('posts', 'media.model_id', '=', 'posts.id')
            ->join('scenes', 'posts.scene_id', '=', 'scenes.id')
            ->where('media.model_type', Post::class)
            ->where('media.collection_name', Post::IMMERSIVE_IMAGES_COLLECTION)
            ->where('scenes.campaign_id', $campaignId)
            ->orderBy('media.id')
            ->pluck('media.id');

        $sceneContentMediaIds = Media::query()
            ->join('scenes', 'media.model_id', '=', 'scenes.id')
            ->where('media.model_type', Scene::class)
            ->where('media.collection_name', Scene::CONTENT_IMAGES_COLLECTION)
            ->where('scenes.campaign_id', $campaignId)
            ->orderBy('media.id')
            ->pluck('media.id');

        $mediaIds = $handoutMediaIds
            ->merge($postMediaIds)
            ->merge($sceneContentMediaIds)
            ->unique()
            ->values();

        foreach ($mediaIds as $mediaId) {
            $media = Media::query()->find((int) $mediaId);

            if ($media instanceof Media) {
                $media->delete();
            }
        }

        foreach ($sceneHeaderPaths as $sceneHeaderPath) {
            $this->db->afterCommit(function () use ($sceneHeaderPath): void {
                $this->sceneHeaderImageStorage->delete((string) $sceneHeaderPath);
            });
        }
    }
}
