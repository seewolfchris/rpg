<?php

declare(strict_types=1);

namespace App\Actions\Character;

use App\Data\Character\UpdateCharacterInput;
use App\Models\Character;
use App\Services\Character\AttributeNormalizer;
use App\Support\CharacterInventoryService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class UpdateCharacterAction
{
    public function __construct(
        private readonly CharacterInventoryService $inventoryService,
        private readonly AttributeNormalizer $attributeNormalizer,
        private readonly DatabaseManager $db,
    ) {}

    public function execute(UpdateCharacterInput $input): void
    {
        $character = $input->character;
        $previousInventory = $this->inventoryService->normalize($character->inventory ?? []);
        $data = $this->attributeNormalizer->normalizeForUpdate($input->payload, $character);
        unset($data['world_id']);
        $stagedAvatar = $this->stageAvatarUpload($input->avatar);
        $replaceAvatar = $stagedAvatar !== null;
        $previousAvatarPath = is_string($character->avatar_path) && $character->avatar_path !== ''
            ? $character->avatar_path
            : null;

        try {
            /** @var int<0, max> $actorUserId */
            $actorUserId = max(0, (int) $input->actor->id);

            $this->db->transaction(function () use (
                $character,
                $data,
                $previousInventory,
                $replaceAvatar,
                $input,
                $previousAvatarPath,
                $stagedAvatar,
                $actorUserId
            ): void {
                $character->fill($data);

                if ($input->removeAvatar && ! $replaceAvatar) {
                    $character->avatar_path = null;
                }

                $character->save();

                $nextInventory = $this->inventoryService->normalize($character->inventory ?? []);
                $operations = $this->inventoryService->diff($previousInventory, $nextInventory);
                $this->inventoryService->log(
                    character: $character,
                    actorUserId: $actorUserId,
                    source: 'character_sheet_update',
                    operations: $operations,
                    context: ['character_id' => (int) $character->id],
                );

                if ($replaceAvatar) {
                    $this->db->connection()->afterCommit(function () use ($character, $stagedAvatar, $previousAvatarPath): void {
                        if ($stagedAvatar === null) {
                            return;
                        }

                        $this->finalizeAvatarReplacement($character, $stagedAvatar, $previousAvatarPath);
                    });

                    return;
                }

                if ($input->removeAvatar && $previousAvatarPath !== null) {
                    $this->db->connection()->afterCommit(function () use ($previousAvatarPath): void {
                        $this->deletePublicFile($previousAvatarPath);
                    });
                }
            });
        } catch (Throwable $exception) {
            $this->discardStagedAvatar($stagedAvatar);

            throw $exception;
        }
    }

    /**
     * @return array{disk: string, staged_path: string, extension: string}|null
     */
    private function stageAvatarUpload(?UploadedFile $avatar): ?array
    {
        if (! $avatar instanceof UploadedFile) {
            return null;
        }

        $stagedPath = $avatar->store('character-avatars/staged', 'public');
        if (! is_string($stagedPath) || trim($stagedPath) === '') {
            throw new \RuntimeException('Avatar-Upload konnte nicht zwischengespeichert werden.');
        }

        $extension = strtolower((string) $avatar->extension());

        return [
            'disk' => 'public',
            'staged_path' => $stagedPath,
            'extension' => $extension !== '' ? $extension : 'jpg',
        ];
    }

    /**
     * @param  array{disk: string, staged_path: string, extension: string}|null  $stagedAvatar
     */
    private function discardStagedAvatar(?array $stagedAvatar): void
    {
        if ($stagedAvatar === null) {
            return;
        }

        $disk = Storage::disk($stagedAvatar['disk']);
        $stagedPath = $stagedAvatar['staged_path'];

        if ($disk->exists($stagedPath)) {
            $disk->delete($stagedPath);
        }
    }

    /**
     * @param  array{disk: string, staged_path: string, extension: string}  $stagedAvatar
     */
    private function finalizeAvatarReplacement(Character $character, array $stagedAvatar, ?string $previousAvatarPath): void
    {
        $disk = Storage::disk($stagedAvatar['disk']);
        $stagedPath = $stagedAvatar['staged_path'];
        $finalPath = 'character-avatars/'.$character->id.'-'.Str::uuid().'.'.$stagedAvatar['extension'];

        try {
            if (! $disk->exists($stagedPath)) {
                throw new \RuntimeException('Zwischengespeicherter Avatar fehlt bei Finalisierung.');
            }

            if (! $disk->move($stagedPath, $finalPath)) {
                throw new \RuntimeException('Zwischengespeicherter Avatar konnte nicht finalisiert werden.');
            }

            $updated = $character->newQuery()
                ->whereKey($character->getKey())
                ->update(['avatar_path' => $finalPath]);

            if ($updated !== 1) {
                throw new \RuntimeException('Avatar-Pfad konnte nach Finalisierung nicht persistiert werden.');
            }

            $character->avatar_path = $finalPath;

            if (
                is_string($previousAvatarPath)
                && $previousAvatarPath !== ''
                && $previousAvatarPath !== $finalPath
            ) {
                $this->deletePublicFile($previousAvatarPath);
            }
        } catch (Throwable $exception) {
            if ($disk->exists($stagedPath)) {
                $disk->delete($stagedPath);
            }

            if ($disk->exists($finalPath)) {
                $disk->delete($finalPath);
            }

            report($exception);
        }
    }

    private function deletePublicFile(string $path): void
    {
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }
}
