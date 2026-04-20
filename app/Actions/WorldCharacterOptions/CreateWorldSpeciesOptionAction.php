<?php

declare(strict_types=1);

namespace App\Actions\WorldCharacterOptions;

use App\Actions\WorldCharacterOptions\Concerns\NormalizesWorldCharacterOptionPayload;
use App\Models\World;
use App\Models\WorldSpecies;
use Illuminate\Database\DatabaseManager;

final class CreateWorldSpeciesOptionAction
{
    use NormalizesWorldCharacterOptionPayload;

    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(World $world, array $data): WorldSpecies
    {
        /** @var WorldSpecies $species */
        $species = $this->db->transaction(function () use ($world, $data): WorldSpecies {
            $lockedWorld = $this->lockAndVerifyContext($world);

            return $this->persistSpecies($lockedWorld, $data);
        }, 3);

        return $species;
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
     * @param  array<string, mixed>  $data
     */
    private function persistSpecies(World $world, array $data): WorldSpecies
    {
        /** @var WorldSpecies $species */
        $species = WorldSpecies::query()->create([
            'world_id' => (int) $world->id,
            'key' => (string) $data['key'],
            'label' => (string) $data['label'],
            'description' => $this->trimNullable($data['description'] ?? null),
            'modifiers_json' => $this->decodeJsonArray($data['modifiers_json'] ?? null),
            'le_bonus' => (int) ($data['le_bonus'] ?? 0),
            'ae_bonus' => (int) ($data['ae_bonus'] ?? 0),
            'position' => (int) ($data['position'] ?? 0),
            'is_magic_capable' => (bool) ($data['is_magic_capable'] ?? false),
            'is_template' => (bool) ($data['is_template'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return $species;
    }
}
