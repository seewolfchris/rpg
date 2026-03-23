<?php

namespace App\Domain\Shared\Exceptions;

use RuntimeException;
use Throwable;

abstract class DomainInvariantViolationException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        private readonly string $reason,
        private readonly string $field,
        string $message,
        private readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function field(): string
    {
        return $this->field;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
