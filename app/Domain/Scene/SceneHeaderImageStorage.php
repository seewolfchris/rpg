<?php

declare(strict_types=1);

namespace App\Domain\Scene;

use App\Models\Scene;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SceneHeaderImageStorage
{
    /**
     * @return array{disk: string, staged_path: string, extension: string}|null
     */
    public function stage(?UploadedFile $file): ?array
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        $stagedPath = $file->store('scene-headers/staged', 'public');
        if (! is_string($stagedPath) || trim($stagedPath) === '') {
            throw new RuntimeException('Headerbild konnte nicht zwischengespeichert werden.');
        }

        $extension = strtolower((string) $file->extension());

        return [
            'disk' => 'public',
            'staged_path' => $stagedPath,
            'extension' => $extension !== '' ? $extension : 'jpg',
        ];
    }

    /**
     * @param  array{disk: string, staged_path: string, extension: string}|null  $stagedHeaderImage
     */
    public function discard(?array $stagedHeaderImage): void
    {
        if ($stagedHeaderImage === null) {
            return;
        }

        $disk = Storage::disk($stagedHeaderImage['disk']);
        $stagedPath = $stagedHeaderImage['staged_path'];

        if ($disk->exists($stagedPath)) {
            $disk->delete($stagedPath);
        }
    }

    /**
     * @param  array{disk: string, staged_path: string, extension: string}  $stagedHeaderImage
     */
    public function finalize(Scene $scene, array $stagedHeaderImage, ?string $previousHeaderPath): void
    {
        $disk = Storage::disk($stagedHeaderImage['disk']);
        $stagedPath = $stagedHeaderImage['staged_path'];
        $finalPath = 'scene-headers/'.$scene->id.'-'.Str::uuid().'.'.$stagedHeaderImage['extension'];

        try {
            if (! $disk->exists($stagedPath)) {
                throw new RuntimeException('Zwischengespeichertes Headerbild fehlt bei Finalisierung.');
            }

            if (! $disk->move($stagedPath, $finalPath)) {
                throw new RuntimeException('Zwischengespeichertes Headerbild konnte nicht finalisiert werden.');
            }

            $updated = $scene->newQuery()
                ->whereKey($scene->getKey())
                ->update(['header_image_path' => $finalPath]);

            if ($updated !== 1) {
                throw new RuntimeException('Headerbild-Pfad konnte nach Finalisierung nicht persistiert werden.');
            }

            $scene->header_image_path = $finalPath;

            if (
                is_string($previousHeaderPath)
                && $previousHeaderPath !== ''
                && $previousHeaderPath !== $finalPath
            ) {
                $this->delete($previousHeaderPath);
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

    public function delete(string $path): void
    {
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }
}
