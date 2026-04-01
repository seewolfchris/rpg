<?php

namespace App\Policies;

use App\Models\Character;
use App\Models\User;

class CharacterPolicy
{
    public function view(User $user, Character $character): bool
    {
        return $this->canManageCharacter($user, $character);
    }

    public function update(User $user, Character $character): bool
    {
        return $this->canManageCharacter($user, $character);
    }

    public function delete(User $user, Character $character): bool
    {
        return $this->canManageCharacter($user, $character);
    }

    public function spendAttributePoints(User $user, Character $character): bool
    {
        return $this->canManageCharacter($user, $character);
    }

    private function canManageCharacter(User $user, Character $character): bool
    {
        return (int) $character->user_id === (int) $user->id || $user->isGmOrAdmin();
    }
}
