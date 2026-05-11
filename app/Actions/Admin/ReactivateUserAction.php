<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

final class ReactivateUserAction
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
                    'user' => 'Nur Admins dürfen Benutzer reaktivieren.',
                ]);
            }

            if (! $lockedTarget->isSuspended()) {
                throw ValidationException::withMessages([
                    'user' => 'Nur gesperrte Accounts können reaktiviert werden.',
                ]);
            }

            $updates = [
                'status' => UserStatus::ACTIVE->value,
                'suspended_at' => null,
                'suspended_by' => null,
                'status_reason' => null,
            ];

            if ($lockedTarget->approved_at === null) {
                $updates['approved_at'] = now();
                $updates['approved_by'] = $lockedActor->id;
            }

            $lockedTarget->forceFill($updates)->save();
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
