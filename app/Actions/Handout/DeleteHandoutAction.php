<?php

namespace App\Actions\Handout;

use App\Domain\Handout\HandoutMutationService;
use App\Models\Handout;

final class DeleteHandoutAction
{
    public function __construct(
        private readonly HandoutMutationService $mutationService,
    ) {}

    public function execute(Handout $handout): void
    {
        $this->mutationService->delete($handout);
    }
}
