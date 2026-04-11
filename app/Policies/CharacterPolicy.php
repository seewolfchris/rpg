<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Character;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\Eloquent\Builder;

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
        if ((int) $character->user_id === (int) $user->id) {
            return true;
        }

        if ($user->hasRole(UserRole::ADMIN)) {
            return true;
        }

        if (! $user->hasRole(UserRole::GM)) {
            return $this->hasAcceptedCoGmAccessForWorld($user, (int) $character->world_id);
        }

        $worldId = (int) $character->world_id;

        if ($worldId <= 0) {
            return false;
        }

        if ($this->resolveActiveWorldId() === $worldId) {
            return true;
        }

        return $this->hasAcceptedCoGmAccessForWorld($user, $worldId);
    }

    private function hasAcceptedCoGmAccessForWorld(User $user, int $worldId): bool
    {
        if ($worldId <= 0) {
            return false;
        }

        return Campaign::query()
            ->where('world_id', $worldId)
            ->whereHas('invitations', function (Builder $invitationQuery) use ($user): void {
                $invitationQuery
                    ->where('user_id', (int) $user->id)
                    ->where('status', CampaignInvitation::STATUS_ACCEPTED)
                    ->where('role', CampaignInvitation::ROLE_CO_GM);
            })
            ->exists();
    }

    private function resolveActiveWorldId(): ?int
    {
        $request = request();

        if ($request !== null) {
            $routeWorld = $request->route('world');

            if ($routeWorld instanceof World) {
                return (int) $routeWorld->id;
            }

            $routeWorldId = data_get($routeWorld, 'id');

            if (is_numeric($routeWorldId)) {
                return (int) $routeWorldId;
            }

            $worldSlug = trim((string) $request->session()->get('world_slug', ''));

            if ($worldSlug !== '') {
                $resolvedWorldId = (int) World::query()
                    ->where('slug', $worldSlug)
                    ->value('id');

                if ($resolvedWorldId > 0) {
                    return $resolvedWorldId;
                }
            }
        }

        $defaultWorldId = (int) World::query()
            ->active()
            ->ordered()
            ->value('id');

        return $defaultWorldId > 0 ? $defaultWorldId : null;
    }
}
