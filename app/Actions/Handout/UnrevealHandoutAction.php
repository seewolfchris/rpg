<?php

namespace App\Actions\Handout;

use App\Models\Handout;
use App\Models\User;
use Illuminate\Database\DatabaseManager;

class UnrevealHandoutAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(Handout $handout, User $actor): Handout
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
