<?php

declare(strict_types=1);

namespace App\Actions\WorldCharacterOptions;

use App\Actions\WorldCharacterOptions\Concerns\NormalizesWorldCharacterOptionPayload;
use App\Models\World;
use App\Models\WorldSpecies;
use Illuminate\Database\DatabaseManager;

final class UpdateWorldSpeciesOptionAction
{
    use NormalizesWorldCharacterOptionPayload;

    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(World $world, WorldSpecies $speciesOption, array $data): void
    {
        $this->db->transaction(function () use ($world, $speciesOption, $data): void {
            $lockedSpeciesOption = $this->lockAndVerifyContext($world, $speciesOption);

            $this->persistSpecies($lockedSpeciesOption, $data);
        }, 3);

        $speciesOption->refresh();
    }

    private function lockAndVerifyContext(World $world, WorldSpecies $speciesOption): WorldSpecies
    {
        /** @var World $lockedWorld */
        $lockedWorld = World::query()
            ->whereKey((int) $world->id)
            ->lockForUpdate()
            ->firstOrFail();

        /** @var WorldSpecies $lockedSpeciesOption */
        $lockedSpeciesOption = WorldSpecies::query()
            ->whereKey((int) $speciesOption->id)
            ->where('world_id', (int) $lockedWorld->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedSpeciesOption;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistSpecies(WorldSpecies $speciesOption, array $data): void
    {
        $speciesOption->update([
            'key' => (string) $data['key'],
            'label' => (string) $data['label'],
            'description' => $this->trimNullable($data['description'] ?? null),
            'modifiers_json' => $this->decodeJsonArray($data['modifiers_json'] ?? null),
            'le_bonus' => (int) ($data['le_bonus'] ?? 0),
            'ae_bonus' => (int) ($data['ae_bonus'] ?? 0),
            'position' => (int) ($data['position'] ?? 0),
            'is_magic_capable' => (bool) ($data['is_magic_capable'] ?? false),
            'is_template' => (bool) ($data['is_template'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);
    }
}
