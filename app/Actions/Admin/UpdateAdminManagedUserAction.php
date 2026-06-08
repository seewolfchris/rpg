<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class UpdateAdminManagedUserAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     password?: string|null,
     *     role: string,
     *     status: string,
     *     can_create_campaigns: bool,
     *     can_post_without_moderation: bool,
     *     status_reason?: string|null
     * }  $attributes
     */
    public function execute(User $actorUser, User $targetUser, array $attributes): void
    {
        $this->db->transaction(function () use ($actorUser, $targetUser, $attributes): void {
            $lockedActor = $this->lockUser($actorUser);
            $lockedTarget = $this->lockUser($targetUser);

            if (! $lockedActor->isAdmin()) {
                throw ValidationException::withMessages([
                    'user' => 'Nur Admins dürfen Benutzer bearbeiten.',
                ]);
            }

            $nextRole = UserRole::from((string) $attributes['role']);
            $nextStatus = UserStatus::from((string) $attributes['status']);

            $this->assertAdminDemotionIsAllowed($lockedActor, $lockedTarget, $nextRole);
            $this->assertAccountStatusChangeIsAllowed($lockedActor, $lockedTarget, $nextStatus);
            $this->persistUser($lockedActor, $lockedTarget, $nextRole, $nextStatus, $attributes);
        }, 3);

        $targetUser->refresh();
    }

    private function lockUser(User $user): User
    {
        /** @var User $lockedUser */
        $lockedUser = User::query()
            ->whereKey((int) $user->id)
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

    private function assertAccountStatusChangeIsAllowed(
        User $actorUser,
        User $targetUser,
        UserStatus $nextStatus,
    ): void {
        if ($nextStatus === UserStatus::ACTIVE) {
            return;
        }

        if ((int) $actorUser->id === (int) $targetUser->id) {
            throw ValidationException::withMessages([
                'user' => 'Du kannst deinen eigenen Account nicht deaktivieren.',
            ]);
        }

        if (! $targetUser->hasRole(UserRole::ADMIN) || ! $targetUser->isActive()) {
            return;
        }

        $activeAdminCount = User::query()
            ->where('role', UserRole::ADMIN->value)
            ->where('status', UserStatus::ACTIVE->value)
            ->lockForUpdate()
            ->count();

        if ($activeAdminCount <= 1) {
            throw ValidationException::withMessages([
                'user' => 'Der letzte aktive Admin kann nicht deaktiviert werden.',
            ]);
        }
    }

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     password?: string|null,
     *     role: string,
     *     status: string,
     *     can_create_campaigns: bool,
     *     can_post_without_moderation: bool,
     *     status_reason?: string|null
     * }  $attributes
     */
    private function persistUser(
        User $actorUser,
        User $targetUser,
        UserRole $nextRole,
        UserStatus $nextStatus,
        array $attributes,
    ): void {
        $statusReason = trim((string) ($attributes['status_reason'] ?? ''));

        $updates = [
            'name' => (string) $attributes['name'],
            'email' => (string) $attributes['email'],
            'role' => $nextRole->value,
            'status' => $nextStatus->value,
            'can_create_campaigns' => (bool) $attributes['can_create_campaigns'],
            'can_post_without_moderation' => (bool) $attributes['can_post_without_moderation'],
        ];

        $password = (string) ($attributes['password'] ?? '');
        if ($password !== '') {
            $updates['password'] = Hash::make($password);
        }

        if ($nextStatus === UserStatus::ACTIVE) {
            if ($targetUser->approved_at === null) {
                $updates['approved_at'] = now();
                $updates['approved_by'] = (int) $actorUser->id;
            }

            $updates['suspended_at'] = null;
            $updates['suspended_by'] = null;
            $updates['status_reason'] = null;
        }

        if ($nextStatus === UserStatus::PENDING) {
            $updates['approved_at'] = null;
            $updates['approved_by'] = null;
            $updates['suspended_at'] = null;
            $updates['suspended_by'] = null;
            $updates['status_reason'] = null;
        }

        if ($nextStatus === UserStatus::SUSPENDED) {
            $updates['suspended_at'] = now();
            $updates['suspended_by'] = (int) $actorUser->id;
            $updates['status_reason'] = $statusReason !== '' ? $statusReason : null;
        }

        $targetUser->forceFill($updates);
        $targetUser->save();
    }
}
