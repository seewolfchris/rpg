<?php

namespace App\Actions\PlayerNote;

use App\Models\PlayerNote;
use Illuminate\Database\DatabaseManager;

final class DeletePlayerNoteAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(PlayerNote $playerNote): void
    {
        $this->db->transaction(function () use ($playerNote): void {
            $playerNote->delete();
        });
    }
}
