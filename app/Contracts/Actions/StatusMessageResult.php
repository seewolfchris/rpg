<?php

declare(strict_types=1);

namespace App\Contracts\Actions;

interface StatusMessageResult
{
    public function statusMessage(): string;
}
