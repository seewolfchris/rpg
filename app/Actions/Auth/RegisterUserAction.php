<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Hash;

final class RegisterUserAction
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function execute(array $data): User
    {
        /** @var User $user */
        $user = $this->db->transaction(function () use ($data): User {
            $this->lockAndVerifyContext((string) $data['email']);

            return $this->persistUser($data);
        }, 3);

        return $user;
    }

    private function lockAndVerifyContext(string $email): void
    {
        User::query()
            ->where('email', $email)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    private function persistUser(array $data): User
    {
        /** @var User $user */
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        return $user;
    }
}
