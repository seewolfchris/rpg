<?php

declare(strict_types=1);

namespace App\Actions\WorldCharacterOptions;

use App\Actions\WorldCharacterOptions\Concerns\NormalizesWorldCharacterOptionPayload;
use App\Models\World;
use App\Models\WorldCalling;
use Illuminate\Database\DatabaseManager;

final class UpdateWorldCallingOptionAction
{
    use NormalizesWorldCharacterOptionPayload;

    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(World $world, WorldCalling $callingOption, array $data): void
    {
        $this->db->transaction(function () use ($world, $callingOption, $data): void {
            $lockedCallingOption = $this->lockAndVerifyContext($world, $callingOption);

            $this->persistCalling($lockedCallingOption, $data);
        }, 3);

        $callingOption->refresh();
    }

    private function lockAndVerifyContext(World $world, WorldCalling $callingOption): WorldCalling
    {
        /** @var World $lockedWorld */
        $lockedWorld = World::query()
            ->whereKey((int) $world->id)
            ->lockForUpdate()
            ->firstOrFail();

        /** @var WorldCalling $lockedCallingOption */
        $lockedCallingOption = WorldCalling::query()
            ->whereKey((int) $callingOption->id)
            ->where('world_id', (int) $lockedWorld->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedCallingOption;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistCalling(WorldCalling $callingOption, array $data): void
    {
        $callingOption->update([
            'key' => (string) $data['key'],
            'label' => (string) $data['label'],
            'description' => $this->trimNullable($data['description'] ?? null),
            'minimums_json' => $this->decodeJsonArray($data['minimums_json'] ?? null),
            'bonuses_json' => $this->decodeJsonArray($data['bonuses_json'] ?? null),
            'position' => (int) ($data['position'] ?? 0),
            'is_magic_capable' => (bool) ($data['is_magic_capable'] ?? false),
            'is_custom' => (bool) ($data['is_custom'] ?? false),
            'is_template' => (bool) ($data['is_template'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);
    }
}
