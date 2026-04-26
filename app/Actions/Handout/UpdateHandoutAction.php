<?php

namespace App\Actions\Handout;

use App\Domain\Handout\HandoutMediaService;
use App\Models\Handout;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;

class UpdateHandoutAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly HandoutMediaService $handoutMediaService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Handout $handout, User $actor, array $data, ?UploadedFile $handoutFile): Handout
    {
        unset($data['handout_file']);

        $this->db->transaction(function () use ($handout, $actor, $data): void {
            $handout->update([
                'scene_id' => isset($data['scene_id']) ? (int) $data['scene_id'] : null,
                'updated_by' => (int) $actor->id,
                'title' => (string) ($data['title'] ?? ''),
                'description' => $data['description'] ?? null,
                'version_label' => $data['version_label'] ?? null,
                'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : null,
            ]);
        });

        if ($handoutFile instanceof UploadedFile) {
            try {
                $this->handoutMediaService->replacePrimaryFile($handout, $handoutFile);
            } catch (Throwable $throwable) {
                report($throwable);

                throw new RuntimeException('Die neue Handout-Datei konnte nicht gespeichert werden.', previous: $throwable);
            }
        }

        $handout->load(['campaign.world', 'scene', 'creator', 'updater', 'media']);

        return $handout;
    }
}
