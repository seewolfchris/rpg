<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class ResetUserPasswordAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function execute(User $user, string $plainPassword): void
    {
        $this->db->transaction(function () use ($user, $plainPassword): void {
            $lockedUser = $this->lockAndVerifyContext($user);

            $this->persistPassword($lockedUser, $plainPassword);
        }, 3);

        $user->refresh();
    }

    private function lockAndVerifyContext(User $user): User
    {
        /** @var User $lockedUser */
        $lockedUser = User::query()
            ->whereKey((int) $user->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedUser;
    }

    private function persistPassword(User $user, string $plainPassword): void
    {
        $user->forceFill([
            'password' => Hash::make($plainPassword),
        ])->setRememberToken(Str::random(60));

        $user->save();
    }
}
