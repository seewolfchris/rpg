<?php

declare(strict_types=1);

namespace App\Actions\World;

use App\Models\Campaign;
use App\Models\Character;
use App\Models\EncyclopediaCategory;
use App\Models\World;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

final class DeleteWorldAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(World $world): void
    {
        $this->db->transaction(function () use ($world): void {
            $lockedWorld = $this->lockAndVerifyContext($world);

            $this->resolveAndValidateDeletion($lockedWorld);
            $this->persistDeletion($lockedWorld);
        }, 3);
    }

    private function lockAndVerifyContext(World $world): World
    {
        /** @var World $lockedWorld */
        $lockedWorld = World::query()
            ->whereKey((int) $world->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedWorld;
    }

    /**
     * @throws ValidationException
     */
    private function resolveAndValidateDeletion(World $world): void
    {
        if ((string) $world->slug === World::defaultSlug()) {
            throw ValidationException::withMessages([
                'world' => 'Die Standardwelt kann nicht gelöscht werden.',
            ]);
        }

        $hasDependencies = Campaign::query()
            ->where('world_id', (int) $world->id)
            ->lockForUpdate()
            ->exists()
            || Character::query()
                ->where('world_id', (int) $world->id)
                ->lockForUpdate()
                ->exists()
            || EncyclopediaCategory::query()
                ->where('world_id', (int) $world->id)
                ->lockForUpdate()
                ->exists();

        if ($hasDependencies) {
            throw ValidationException::withMessages([
                'world' => 'Diese Welt kann nicht gelöscht werden, solange noch Kampagnen, Charaktere oder Wissen daran hängen.',
            ]);
        }
    }

    private function persistDeletion(World $world): void
    {
        $world->delete();
    }
}
