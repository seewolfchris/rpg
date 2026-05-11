<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

final class SuspendUserAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(User $actorUser, User $targetUser, ?string $statusReason = null): void
    {
        $this->db->transaction(function () use ($actorUser, $targetUser, $statusReason): void {
            $lockedActor = $this->lockAndVerifyContext($actorUser);
            $lockedTarget = $this->lockAndVerifyContext($targetUser);

            if (! $lockedActor->isAdmin()) {
                throw ValidationException::withMessages([
                    'user' => 'Nur Admins dürfen Benutzer sperren.',
                ]);
            }

            if ((int) $lockedActor->id === (int) $lockedTarget->id) {
                throw ValidationException::withMessages([
                    'user' => 'Du kannst deinen eigenen Account nicht sperren.',
                ]);
            }

            if ($lockedTarget->isSuspended()) {
                throw ValidationException::withMessages([
                    'user' => 'Der Account ist bereits gesperrt.',
                ]);
            }

            $this->assertActiveAdminSuspensionIsAllowed($lockedTarget);

            $reason = $statusReason !== null ? trim($statusReason) : '';

            $lockedTarget->forceFill([
                'status' => UserStatus::SUSPENDED->value,
                'suspended_at' => now(),
                'suspended_by' => $lockedActor->id,
                'status_reason' => $reason !== '' ? $reason : null,
            ])->save();
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

    private function assertActiveAdminSuspensionIsAllowed(User $targetUser): void
    {
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
                'user' => 'Der letzte aktive Admin kann nicht gesperrt werden.',
            ]);
        }
    }
}
