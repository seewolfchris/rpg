<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

final class UpdateUserModerationPermissionAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array{role: string, can_create_campaigns: bool, can_post_without_moderation: bool}  $attributes
     */
    public function execute(User $actorUser, User $targetUser, array $attributes): void
    {
        $this->db->transaction(function () use ($actorUser, $targetUser, $attributes): void {
            $lockedActor = $this->lockAndVerifyContext($actorUser);
            $lockedTarget = $this->lockAndVerifyContext($targetUser);

            if (! $lockedActor->isAdmin()) {
                throw ValidationException::withMessages([
                    'user' => 'Nur Admins dürfen Plattformrechte ändern.',
                ]);
            }

            $nextRole = UserRole::from((string) $attributes['role']);

            $this->assertAdminDemotionIsAllowed($lockedActor, $lockedTarget, $nextRole);
            $this->persistPlatformRights($lockedTarget, $nextRole, $attributes);
        }, 3);

        $targetUser->refresh();
    }

    private function lockAndVerifyContext(User $targetUser): User
    {
        /** @var User $lockedUser */
        $lockedUser = User::query()
            ->whereKey((int) $targetUser->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedUser;
    }

    private function assertAdminDemotionIsAllowed(User $actorUser, User $targetUser, UserRole $nextRole): void
    {
        if (! $targetUser->hasRole(UserRole::ADMIN) || $nextRole === UserRole::ADMIN) {
            return;
        }

        $adminCount = User::query()
            ->where('role', UserRole::ADMIN->value)
            ->lockForUpdate()
            ->count();

        if ($adminCount <= 1) {
            throw ValidationException::withMessages([
                'user' => 'Der letzte Admin kann nicht degradiert werden.',
            ]);
        }

        if ((int) $actorUser->id === (int) $targetUser->id) {
            throw ValidationException::withMessages([
                'user' => 'Du kannst dir die eigene Admin-Rolle nicht entziehen.',
            ]);
        }
    }

    /**
     * @param  array{role: string, can_create_campaigns: bool, can_post_without_moderation: bool}  $attributes
     */
    private function persistPlatformRights(User $targetUser, UserRole $nextRole, array $attributes): void
    {
        $targetUser->forceFill([
            'role' => $nextRole->value,
            'can_create_campaigns' => (bool) $attributes['can_create_campaigns'],
            'can_post_without_moderation' => (bool) $attributes['can_post_without_moderation'],
        ]);
        $targetUser->save();
    }
}
