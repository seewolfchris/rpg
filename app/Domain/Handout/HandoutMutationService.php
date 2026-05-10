<?php

namespace App\Domain\Handout;

use App\Models\Handout;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

class HandoutMutationService
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function delete(Handout $handout): void
    {
        $this->db->transaction(function () use ($handout): void {
            $handout->delete();
        });
    }

    public function reveal(Handout $handout, User $actor): Handout
    {
        $this->db->transaction(function () use ($handout, $actor): void {
            $handout->update([
                'revealed_at' => now(),
                'updated_by' => (int) $actor->id,
            ]);
        });

        return $handout;
    }

    public function unreveal(Handout $handout, User $actor): Handout
    {
        $this->db->transaction(function () use ($handout, $actor): void {
            $handout->update([
                'revealed_at' => null,
                'updated_by' => (int) $actor->id,
            ]);
        });

        return $handout;
    }
}
