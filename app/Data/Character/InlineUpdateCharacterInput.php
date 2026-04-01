<?php

declare(strict_types=1);

namespace App\Data\Character;

use App\Models\Character;

final readonly class InlineUpdateCharacterInput
{
    public function __construct(
        public Character $character,
        /** @var array<string, mixed> */
        public array $payload,
        public bool $isHtmxRequest,
    ) {}
}

