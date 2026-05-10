<?php

namespace App\Actions\Handout;

use App\Domain\Handout\HandoutMutationService;
use App\Models\Handout;
use App\Models\User;

class RevealHandoutAction
{
    public function __construct(
        private readonly HandoutMutationService $mutationService,
    ) {}

    public function execute(Handout $handout, User $actor): Handout
    {
        return $this->mutationService->reveal($handout, $actor);
    }
}
