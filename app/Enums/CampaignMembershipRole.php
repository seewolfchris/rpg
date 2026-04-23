<?php

namespace App\Enums;

enum CampaignMembershipRole: string
{
    case PLAYER = 'player';
    case TRUSTED_PLAYER = 'trusted_player';
    case GM = 'gm';
}
