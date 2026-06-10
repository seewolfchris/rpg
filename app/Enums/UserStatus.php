<?php

namespace App\Enums;

enum UserStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Aktiv',
            self::PENDING => 'Ausstehend',
            self::SUSPENDED => 'Gesperrt',
        };
    }

    public static function labelFor(string $status): string
    {
        return self::tryFrom($status)?->label() ?? $status;
    }
}
