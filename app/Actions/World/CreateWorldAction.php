<?php

declare(strict_types=1);

namespace App\Actions\World;

use App\Models\World;
use Illuminate\Database\DatabaseManager;

final class CreateWorldAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): World
    {
        /** @var World $world */
        $world = $this->db->transaction(function () use ($data): World {
            $this->lockAndVerifySlugContext((string) ($data['slug'] ?? ''));

            return $this->persistWorld($data);
        }, 3);

        return $world;
    }

    private function lockAndVerifySlugContext(string $slug): void
    {
        if ($slug === '') {
            return;
        }

        World::query()
            ->where('slug', $slug)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistWorld(array $data): World
    {
        /** @var World $world */
        $world = World::query()->create($data);

        return $world;
    }
}
