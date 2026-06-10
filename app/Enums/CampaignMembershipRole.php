<?php

namespace App\Enums;

enum CampaignMembershipRole: string
{
    case PLAYER = 'player';
    case TRUSTED_PLAYER = 'trusted_player';
    case GM = 'gm';

    public function label(): string
    {
        return match ($this) {
            self::GM => 'SL',
            self::TRUSTED_PLAYER => 'Vertrauensspieler',
            self::PLAYER => 'Spieler',
        };
    }

    public static function labelFor(string $role): string
    {
        return self::tryFrom($role)?->label() ?? $role;
    }
}
