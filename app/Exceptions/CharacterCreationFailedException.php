<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

class CharacterCreationFailedException extends Exception
{
    public static function fromThrowable(Throwable $throwable): self
    {
        return new self(
            message: 'Character creation failed.',
            code: (int) $throwable->getCode(),
            previous: $throwable,
        );
    }
}
