<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DeleteAdminManagedUserAction
{
    /**
     * @var list<array{0: string, 1: string}>
     */
    private const RETAINED_USER_REFERENCES = [
        ['posts', 'user_id'],
        ['campaigns', 'owner_id'],
        ['characters', 'user_id'],
        ['scenes', 'created_by'],
        ['story_log_entries', 'created_by'],
        ['handouts', 'created_by'],
        ['campaign_gm_contact_threads', 'created_by'],
        ['campaign_gm_contact_messages', 'user_id'],
        ['dice_rolls', 'user_id'],
        ['post_mentions', 'mentioned_user_id'],
    ];

    /**
     * @var list<array{0: string, 1: string}>
     */
    private const PERSONAL_USER_REFERENCES = [
        ['campaign_memberships', 'user_id'],
        ['campaign_invitations', 'user_id'],
        ['scene_bookmarks', 'user_id'],
        ['scene_subscriptions', 'user_id'],
        ['post_reactions', 'user_id'],
        ['player_notes', 'user_id'],
        ['point_events', 'user_id'],
        ['post_scene_notification_deliveries', 'recipient_user_id'],
        ['campaign_role_events', 'target_user_id'],
        ['sessions', 'user_id'],
    ];

    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(User $actorUser, User $targetUser): void
    {
        $this->db->transaction(function () use ($actorUser, $targetUser): void {
            $lockedActor = $this->lockUser($actorUser);
            $lockedTarget = $this->lockUser($targetUser);

            $this->assertDeletionIsAllowed($lockedActor, $lockedTarget);

            $targetUserId = (int) $lockedTarget->id;
            $targetEmail = (string) $lockedTarget->email;
            $systemUser = $this->needsSystemUser($targetUserId)
                ? $this->ensureDeletedUserSystemAccount()
                : null;

            if ($systemUser instanceof User) {
                $this->reassignRetainedContent($targetUserId, (int) $systemUser->id);
            }

            $this->deletePersonalReferences($targetUserId, $targetEmail);
            $this->deletePushSubscriptions($targetUserId);
            $this->deleteNotifications($targetUserId);

            $lockedTarget->forceFill(['remember_token' => null]);
            $lockedTarget->save();
            $lockedTarget->delete();
        }, 3);
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

    private function assertDeletionIsAllowed(User $actorUser, User $targetUser): void
    {
        if (! $actorUser->isAdmin()) {
            throw ValidationException::withMessages([
                'user' => 'Nur Admins dürfen Benutzer endgültig entfernen.',
            ]);
        }

        if ($targetUser->isDeletedUserSystemAccount()) {
            throw ValidationException::withMessages([
                'user' => 'Das technische Systemkonto kann nicht entfernt werden.',
            ]);
        }

        if ((int) $actorUser->id === (int) $targetUser->id) {
            throw ValidationException::withMessages([
                'user' => 'Du kannst deinen eigenen Account nicht entfernen.',
            ]);
        }

        if (! $targetUser->hasRole(UserRole::ADMIN)) {
            return;
        }

        $adminCount = User::query()
            ->where('role', UserRole::ADMIN->value)
            ->lockForUpdate()
            ->count();

        if ($adminCount <= 1) {
            throw ValidationException::withMessages([
                'user' => 'Der letzte Admin kann nicht entfernt werden.',
            ]);
        }

        if (! $targetUser->isActive()) {
            return;
        }

        $activeAdminCount = User::query()
            ->where('role', UserRole::ADMIN->value)
            ->where('status', UserStatus::ACTIVE->value)
            ->lockForUpdate()
            ->count();

        if ($activeAdminCount <= 1) {
            throw ValidationException::withMessages([
                'user' => 'Der letzte aktive Admin kann nicht entfernt werden.',
            ]);
        }
    }

    private function needsSystemUser(int $targetUserId): bool
    {
        foreach (self::RETAINED_USER_REFERENCES as [$table, $column]) {
            if ($this->db->table($table)->where($column, $targetUserId)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function ensureDeletedUserSystemAccount(): User
    {
        $systemUser = User::query()
            ->where('email', User::DELETED_USER_SYSTEM_EMAIL)
            ->lockForUpdate()
            ->first();

        if (! $systemUser instanceof User) {
            $systemUser = new User();
        }

        $systemUser->forceFill([
            'name' => User::DELETED_USER_SYSTEM_NAME,
            'email' => User::DELETED_USER_SYSTEM_EMAIL,
            'email_verified_at' => null,
            'password' => Hash::make(Str::random(80)),
            'remember_token' => null,
            'role' => UserRole::PLAYER->value,
            'status' => UserStatus::SUSPENDED->value,
            'can_create_campaigns' => false,
            'can_post_without_moderation' => false,
            'offline_queue_enabled' => false,
            'approved_at' => null,
            'approved_by' => null,
            'suspended_at' => now(),
            'suspended_by' => null,
            'status_reason' => 'Technisches Systemkonto für erhaltene Inhalte gelöschter Benutzer.',
            'terms_accepted_at' => null,
            'terms_version' => null,
        ]);
        $systemUser->save();

        return $systemUser;
    }

    private function reassignRetainedContent(int $targetUserId, int $systemUserId): void
    {
        foreach (self::RETAINED_USER_REFERENCES as [$table, $column]) {
            $this->db->table($table)
                ->where($column, $targetUserId)
                ->update([$column => $systemUserId]);
        }
    }

    private function deletePersonalReferences(int $targetUserId, string $targetEmail): void
    {
        foreach (self::PERSONAL_USER_REFERENCES as [$table, $column]) {
            $this->db->table($table)
                ->where($column, $targetUserId)
                ->delete();
        }

        $this->db->table('password_reset_tokens')
            ->where('email', $targetEmail)
            ->delete();
    }

    private function deletePushSubscriptions(int $targetUserId): void
    {
        $table = (string) config('webpush.table_name', 'push_subscriptions');
        $connectionName = config('webpush.database_connection');
        $connection = is_string($connectionName) && $connectionName !== ''
            ? $this->db->connection($connectionName)
            : $this->db->connection();

        $connection->table($table)
            ->where('user_id', $targetUserId)
            ->delete();
    }

    private function deleteNotifications(int $targetUserId): void
    {
        $this->db->table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $targetUserId)
            ->delete();
    }
}
