<?php

declare(strict_types=1);

namespace App\Services\Character;

use App\Exceptions\CharacterCreationFailedException;
use App\Http\Requests\Character\StoreCharacterRequest;
use App\Models\Character;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AvatarService
{
    /**
     * @return array{disk: string, staged_path: string, extension: string}|null
     */
    public function stageFromRequest(StoreCharacterRequest $request): ?array
    {
        if (! $request->hasFile('avatar')) {
            return null;
        }

        $file = $request->file('avatar');
        if ($file === null) {
            return null;
        }

        $stagedPath = $file->store('character-avatars/staged', 'public');
        if (! is_string($stagedPath) || trim($stagedPath) === '') {
            throw new CharacterCreationFailedException('Unable to stage avatar upload.');
        }

        $extension = strtolower((string) $file->extension());

        return [
            'disk' => 'public',
            'staged_path' => $stagedPath,
            'extension' => $extension !== '' ? $extension : 'jpg',
        ];
    }

    /**
     * @param  array{disk: string, staged_path: string, extension: string}|null  $stagedAvatar
     */
    public function discardStageIfPresent(?array $stagedAvatar): void
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
     *
     * @throws CharacterCreationFailedException
     */
    public function finalizeForCharacter(array $stagedAvatar, Character $character): void
    {
        $disk = Storage::disk($stagedAvatar['disk']);
        $stagedPath = $stagedAvatar['staged_path'];
        $finalPath = 'character-avatars/'.$character->id.'-'.Str::uuid().'.'.$stagedAvatar['extension'];

        try {
            if (! $disk->exists($stagedPath)) {
                throw new CharacterCreationFailedException('Staged avatar file is missing.');
            }

            $moved = $disk->move($stagedPath, $finalPath);
            if (! $moved) {
                throw new CharacterCreationFailedException('Unable to move staged avatar file.');
            }

            $character->forceFill([
                'avatar_path' => $finalPath,
            ])->saveOrFail();
        } catch (Throwable $throwable) {
            $this->cleanupFinalizationFailure($stagedAvatar['disk'], $stagedPath, $finalPath, $character);

            if ($throwable instanceof CharacterCreationFailedException) {
                throw $throwable;
            }

            throw CharacterCreationFailedException::fromThrowable($throwable);
        }
    }

    private function cleanupFinalizationFailure(
        string $diskName,
        string $stagedPath,
        string $finalPath,
        Character $character,
    ): void {
        $disk = Storage::disk($diskName);

        if ($disk->exists($stagedPath)) {
            $disk->delete($stagedPath);
        }

        if ($disk->exists($finalPath)) {
            $disk->delete($finalPath);
        }

        if (! $character->exists) {
            return;
        }

        try {
            $character->newQuery()
                ->whereKey($character->getKey())
                ->update(['avatar_path' => null]);
            $character->avatar_path = null;
        } catch (Throwable) {
            // Cleanup must stay best-effort and must not hide the original exception.
        }
    }
}
