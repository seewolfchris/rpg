<?php

namespace App\Actions\Handout;

use App\Models\Handout;
use Illuminate\Database\DatabaseManager;

class DeleteHandoutAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(Handout $handout): void
    {
        $this->db->transaction(function () use ($handout): void {
            $handout->delete();
        });
    }
}
