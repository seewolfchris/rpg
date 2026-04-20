<?php

declare(strict_types=1);

namespace App\Actions\Post;

use App\Domain\Post\StorePostResult;
use App\Domain\Post\StorePostService;
use App\Models\Campaign;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

final class StorePostAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly StorePostService $storePostService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Scene $scene, User $author, array $data): StorePostResult
    {
        /** @var array{scene: Scene, isModerator: bool, requiresApproval: bool, worldSlug: string|null} $context */
        $context = $this->db->transaction(function () use ($scene, $author): array {
            [$lockedScene, $lockedCampaign] = $this->lockAndVerifyContext($scene);

            return $this->resolveModerationContext($lockedScene, $lockedCampaign, $author);
        }, 3);

        return $this->storePostService->store(
            scene: $context['scene'],
            user: $author,
            data: $data,
            isModerator: $context['isModerator'],
            requiresApproval: $context['requiresApproval'],
            worldSlug: $context['worldSlug'],
        );
    }

    /**
     * @return array{0: Scene, 1: Campaign}
     */
    private function lockAndVerifyContext(Scene $scene): array
    {
        /** @var Campaign $lockedCampaign */
        $lockedCampaign = Campaign::query()
            ->whereKey((int) $scene->campaign_id)
            ->whereHas('world')
            ->lockForUpdate()
            ->firstOrFail();

        /** @var Scene $lockedScene */
        $lockedScene = Scene::query()
            ->whereKey((int) $scene->id)
            ->where('campaign_id', (int) $lockedCampaign->id)
            ->lockForUpdate()
            ->firstOrFail();

        return [$lockedScene, $lockedCampaign];
    }

    /**
     * @return array{scene: Scene, isModerator: bool, requiresApproval: bool, worldSlug: string|null}
     */
    private function resolveModerationContext(Scene $scene, Campaign $campaign, User $author): array
    {
        $campaign->loadMissing('world');

        $isModerator = $author->isGmOrAdmin() || $campaign->isCoGm($author);
        $requiresApproval = $campaign->requiresPostModeration()
            && ! $campaign->userCanPostWithoutModeration($author)
            && ! $isModerator;
        $worldSlug = $campaign->world?->slug;

        return [
            'scene' => $scene,
            'isModerator' => $isModerator,
            'requiresApproval' => $requiresApproval,
            'worldSlug' => is_string($worldSlug) && $worldSlug !== '' ? $worldSlug : null,
        ];
    }
}
