<?php

declare(strict_types=1);

namespace App\Actions\Encyclopedia;

use App\Models\EncyclopediaCategory;
use App\Models\World;
use Illuminate\Database\DatabaseManager;

final class CreateEncyclopediaCategoryAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(World $world, array $data): EncyclopediaCategory
    {
        /** @var EncyclopediaCategory $category */
        $category = $this->db->transaction(function () use ($world, $data): EncyclopediaCategory {
            $lockedWorld = $this->lockAndVerifyContext($world);

            return $this->persistCategory($lockedWorld, $data);
        }, 3);

        return $category;
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
    private function persistCategory(World $world, array $data): EncyclopediaCategory
    {
        /** @var EncyclopediaCategory $category */
        $category = EncyclopediaCategory::query()->create(array_merge($data, [
            'world_id' => (int) $world->id,
        ]));

        return $category;
    }
}
