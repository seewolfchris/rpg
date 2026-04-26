<?php

namespace App\Actions\Handout;

use App\Domain\Handout\HandoutMediaService;
use App\Models\Campaign;
use App\Models\Handout;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;

class StoreHandoutAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly HandoutMediaService $handoutMediaService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Campaign $campaign, User $actor, array $data, UploadedFile $handoutFile): Handout
    {
        unset($data['handout_file']);

        /** @var Handout $handout */
        $handout = $this->db->transaction(function () use ($campaign, $actor, $data): Handout {
            /** @var Handout $createdHandout */
            $createdHandout = Handout::query()->create([
                'campaign_id' => (int) $campaign->id,
                'scene_id' => isset($data['scene_id']) ? (int) $data['scene_id'] : null,
                'created_by' => (int) $actor->id,
                'updated_by' => null,
                'title' => (string) ($data['title'] ?? ''),
                'description' => $data['description'] ?? null,
                'revealed_at' => null,
                'version_label' => $data['version_label'] ?? null,
                'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : null,
            ]);

            return $createdHandout;
        });

        try {
            $this->handoutMediaService->attachPrimaryFile($handout, $handoutFile);
        } catch (Throwable $throwable) {
            report($throwable);

            try {
                $handout->delete();
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            }

            throw new RuntimeException('Die Handout-Datei konnte nicht gespeichert werden.', previous: $throwable);
        }

        $handout->load(['campaign.world', 'scene', 'creator', 'updater', 'media']);

        return $handout;
    }
}
