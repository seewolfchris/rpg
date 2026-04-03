<?php

declare(strict_types=1);

namespace App\Actions\World;

use App\Exceptions\DefaultWorldConfigurationException;
use App\Models\World;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class UpdateWorldAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function execute(World $world, array $data): void
    {
        $worldId = (int) $world->id;

        $this->db->transaction(function () use ($worldId, $data): void {
            $lockedWorld = $this->lockWorldOrFail($worldId);

            $this->validateAndApplyUpdate($lockedWorld, $data);
        }, 3);

        $world->refresh();
    }

    /**
     * @throws ValidationException
     */
    public function toggleActive(World $world): bool
    {
        $worldId = (int) $world->id;
        $nextIsActive = false;

        $this->db->transaction(function () use ($worldId, &$nextIsActive): void {
            $lockedWorld = $this->lockWorldOrFail($worldId);
            $nextIsActive = ! (bool) $lockedWorld->is_active;

            $this->validateAndApplyUpdate($lockedWorld, [
                'is_active' => $nextIsActive,
            ]);
        }, 3);

        $world->refresh();

        return $nextIsActive;
    }

    private function lockWorldOrFail(int $worldId): World
    {
        /** @var World $world */
        $world = World::query()
            ->whereKey($worldId)
            ->lockForUpdate()
            ->firstOrFail();

        return $world;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function validateAndApplyUpdate(World $world, array $data): void
    {
        try {
            $configuredDefaultWorld = World::resolveConfiguredDefaultOrFail(requireActive: false);
        } catch (DefaultWorldConfigurationException) {
            throw ValidationException::withMessages([
                'slug' => 'Die Standardwelt-Konfiguration ist inkonsistent. Bitte worlds.default_slug und Datenbank synchronisieren.',
            ]);
        }

        $nextSlug = isset($data['slug'])
            ? trim((string) $data['slug'])
            : (string) $world->slug;
        $nextIsActive = array_key_exists('is_active', $data)
            ? (bool) $data['is_active']
            : (bool) $world->is_active;
        $defaultSlug = (string) $configuredDefaultWorld->slug;
        $isConfiguredDefaultWorld = (int) $world->id === (int) $configuredDefaultWorld->id;
        $errors = [];

        if ($isConfiguredDefaultWorld && $nextSlug !== $defaultSlug) {
            $errors['slug'] = 'Der Slug der Standardwelt kann nicht geändert werden.';
        }

        if ($isConfiguredDefaultWorld && ! $nextIsActive) {
            $errors['is_active'] = 'Die Standardwelt kann nicht deaktiviert werden.';
        }

        if (! $nextIsActive) {
            $otherActiveWorldExists = World::query()
                ->whereKeyNot((int) $world->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->exists();

            if (! $otherActiveWorldExists && ! array_key_exists('is_active', $errors)) {
                $errors['is_active'] = 'Mindestens eine aktive Welt muss bestehen bleiben.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $world->update($data);
    }
}
