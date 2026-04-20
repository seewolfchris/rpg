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

    public function execute(User $targetUser, bool $enabled): void
    {
        $this->db->transaction(function () use ($targetUser, $enabled): void {
            $lockedUser = $this->lockAndVerifyContext($targetUser);
            $nextPermission = $this->resolveAndValidatePermission($lockedUser, $enabled);

            $this->persistPermission($lockedUser, $nextPermission);
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

    private function resolveAndValidatePermission(User $targetUser, bool $enabled): bool
    {
        $isTargetPlayer = $targetUser->hasRole(UserRole::PLAYER);

        if (! $isTargetPlayer && $enabled) {
            throw ValidationException::withMessages([
                'user' => 'Das Recht kann nur für Spieler aktiviert werden.',
            ]);
        }

        return $isTargetPlayer ? $enabled : false;
    }

    private function persistPermission(User $targetUser, bool $nextPermission): void
    {
        $targetUser->forceFill([
            'can_post_without_moderation' => $nextPermission,
        ]);
        $targetUser->save();
    }
}
