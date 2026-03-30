<?php

declare(strict_types=1);

namespace App\Actions\Character;

use App\Models\Character;

final readonly class UpdateCharacterInlineResult
{
    public function __construct(
        public Character $character,
        public bool $shouldRenderFragment,
    ) {}
}
