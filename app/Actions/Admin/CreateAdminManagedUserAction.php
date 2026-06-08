<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class CreateAdminManagedUserAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     password: string,
     *     role: string,
     *     status: string,
     *     can_create_campaigns: bool,
     *     can_post_without_moderation: bool,
     *     status_reason?: string|null
     * }  $attributes
     */
    public function execute(User $actorUser, array $attributes): User
    {
        /** @var User $user */
        $user = $this->db->transaction(function () use ($actorUser, $attributes): User {
            $lockedActor = $this->lockUser($actorUser);

            if (! $lockedActor->isAdmin()) {
                throw ValidationException::withMessages([
                    'user' => 'Nur Admins dürfen Benutzer erstellen.',
                ]);
            }

            if (strcasecmp((string) $attributes['email'], User::DELETED_USER_SYSTEM_EMAIL) === 0) {
                throw ValidationException::withMessages([
                    'email' => 'Diese E-Mail-Adresse ist für ein technisches Systemkonto reserviert.',
                ]);
            }

            return $this->persistUser($lockedActor, $attributes);
        }, 3);

        return $user;
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

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     password: string,
     *     role: string,
     *     status: string,
     *     can_create_campaigns: bool,
     *     can_post_without_moderation: bool,
     *     status_reason?: string|null
     * }  $attributes
     */
    private function persistUser(User $actorUser, array $attributes): User
    {
        $status = UserStatus::from((string) $attributes['status']);
        $role = UserRole::from((string) $attributes['role']);
        $statusReason = trim((string) ($attributes['status_reason'] ?? ''));

        $updates = [
            'name' => (string) $attributes['name'],
            'email' => (string) $attributes['email'],
            'password' => Hash::make((string) $attributes['password']),
            'role' => $role->value,
            'status' => $status->value,
            'can_create_campaigns' => (bool) $attributes['can_create_campaigns'],
            'can_post_without_moderation' => (bool) $attributes['can_post_without_moderation'],
            'terms_accepted_at' => null,
            'terms_version' => null,
            'approved_at' => null,
            'approved_by' => null,
            'suspended_at' => null,
            'suspended_by' => null,
            'status_reason' => null,
        ];

        if ($status === UserStatus::ACTIVE) {
            $updates['approved_at'] = now();
            $updates['approved_by'] = (int) $actorUser->id;
        }

        if ($status === UserStatus::SUSPENDED) {
            $updates['suspended_at'] = now();
            $updates['suspended_by'] = (int) $actorUser->id;
            $updates['status_reason'] = $statusReason !== '' ? $statusReason : null;
        }

        $user = new User();
        $user->forceFill($updates);
        $user->save();

        return $user;
    }
}
