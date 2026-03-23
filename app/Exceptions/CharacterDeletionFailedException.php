<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

class CharacterDeletionFailedException extends Exception
{
    public static function fromThrowable(Throwable $throwable): self
    {
        return new self(
            message: 'Character deletion failed.',
            code: 0,
            previous: $throwable,
        );
    }
}
