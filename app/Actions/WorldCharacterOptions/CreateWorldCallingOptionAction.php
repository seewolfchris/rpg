<?php

declare(strict_types=1);

namespace App\Actions\WorldCharacterOptions;

use App\Actions\WorldCharacterOptions\Concerns\NormalizesWorldCharacterOptionPayload;
use App\Models\World;
use App\Models\WorldCalling;
use Illuminate\Database\DatabaseManager;

final class CreateWorldCallingOptionAction
{
    use NormalizesWorldCharacterOptionPayload;

    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(World $world, array $data): WorldCalling
    {
        /** @var WorldCalling $calling */
        $calling = $this->db->transaction(function () use ($world, $data): WorldCalling {
            $lockedWorld = $this->lockAndVerifyContext($world);

            return $this->persistCalling($lockedWorld, $data);
        }, 3);

        return $calling;
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
    private function persistCalling(World $world, array $data): WorldCalling
    {
        /** @var WorldCalling $calling */
        $calling = WorldCalling::query()->create([
            'world_id' => (int) $world->id,
            'key' => (string) $data['key'],
            'label' => (string) $data['label'],
            'description' => $this->trimNullable($data['description'] ?? null),
            'minimums_json' => $this->decodeJsonArray($data['minimums_json'] ?? null),
            'bonuses_json' => $this->decodeJsonArray($data['bonuses_json'] ?? null),
            'position' => (int) ($data['position'] ?? 0),
            'is_magic_capable' => (bool) ($data['is_magic_capable'] ?? false),
            'is_custom' => (bool) ($data['is_custom'] ?? false),
            'is_template' => (bool) ($data['is_template'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return $calling;
    }
}
