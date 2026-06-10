<?php

namespace App\Enums;

enum UserRole: string
{
    case PLAYER = 'player';
    case ADMIN = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin',
            self::PLAYER => 'Spieler',
        };
    }

    public static function labelFor(string $role): string
    {
        return self::tryFrom($role)?->label() ?? $role;
    }
}
