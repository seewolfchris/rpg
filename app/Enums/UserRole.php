<?php

namespace App\Enums;

enum UserRole: string
{
    case PLAYER = 'player';
    case GM = 'gm';
    case ADMIN = 'admin';
}
