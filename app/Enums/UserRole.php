<?php

namespace App\Enums;

enum UserRole: string
{
    case PLAYER = 'player';
    case ADMIN = 'admin';
}
