<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

final class ApproveUserAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(User $actorUser, User $targetUser): void
    {
        $this->db->transaction(function () use ($actorUser, $targetUser): void {
            $lockedActor = $this->lockAndVerifyContext($actorUser);
            $lockedTarget = $this->lockAndVerifyContext($targetUser);

            if (! $lockedActor->isAdmin()) {
                throw ValidationException::withMessages([
                    'user' => 'Nur Admins dürfen Benutzer freischalten.',
                ]);
            }

            if (! $lockedTarget->isPending() && ! $lockedTarget->isSuspended()) {
                throw ValidationException::withMessages([
                    'user' => 'Nur wartende oder gesperrte Accounts können freigeschaltet werden.',
                ]);
            }

            $lockedTarget->forceFill([
                'status' => UserStatus::ACTIVE->value,
                'approved_at' => now(),
                'approved_by' => $lockedActor->id,
                'suspended_at' => null,
                'suspended_by' => null,
                'status_reason' => null,
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
}
