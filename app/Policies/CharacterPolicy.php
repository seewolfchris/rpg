<?php

namespace App\Policies;

use App\Domain\Campaign\CampaignAccess;
use App\Enums\UserRole;
use App\Models\Character;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CharacterPolicy
{
    public function __construct(
        private readonly CampaignAccess $campaignAccess,
    ) {}

    public function view(User $user, Character $character): bool
    {
        if ($this->canManageCharacter($user, $character)) {
            return true;
        }

        return $this->hasParticipantAccessViaCharacterPosts($user, $character);
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
        if ((int) $character->user_id === (int) $user->id) {
            return true;
        }

        if ($user->hasRole(UserRole::ADMIN)) {
            return true;
        }

        return $this->hasAcceptedCoGmAccessForWorld($user, (int) $character->world_id);
    }

    private function hasParticipantAccessViaCharacterPosts(User $user, Character $character): bool
    {
        $characterId = (int) $character->id;
        $worldId = (int) $character->world_id;

        if ($characterId <= 0 || $worldId <= 0) {
            return false;
        }

        return Character::query()
            ->whereKey($characterId)
            ->where('world_id', $worldId)
            ->whereHas('posts.scene.campaign', function (Builder $campaignQuery) use ($user): void {
                $campaignQuery
                    ->whereColumn('campaigns.world_id', 'characters.world_id');
                $this->applyParticipantCampaignConstraint($campaignQuery, $user);
            })
            ->exists();
    }

    private function hasAcceptedCoGmAccessForWorld(User $user, int $worldId): bool
    {
        return $this->campaignAccess->hasAcceptedCoGmAccessForWorld($user, $worldId);
    }

    /**
     * @param  Builder<Model>  $campaignQuery
     */
    private function applyParticipantCampaignConstraint(Builder $campaignQuery, User $user): void
    {
        $this->campaignAccess->applyParticipantCampaignConstraint($campaignQuery, $user);
    }

}
