<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class DefaultWorldConfigurationException extends RuntimeException
{
    public static function worldsTableMissing(string $defaultSlug): self
    {
        return new self(
            "World context configuration is invalid: the 'worlds' table is missing while WORLD_DEFAULT_SLUG is set to '{$defaultSlug}'. Run migrations before serving requests."
        );
    }

    public static function worldMissing(string $defaultSlug): self
    {
        return new self(
            "World context configuration is invalid: WORLD_DEFAULT_SLUG '{$defaultSlug}' does not exist in the 'worlds' table."
        );
    }

    public static function worldInactive(string $defaultSlug): self
    {
        return new self(
            "World context configuration is invalid: WORLD_DEFAULT_SLUG '{$defaultSlug}' points to an inactive world."
        );
    }
}
