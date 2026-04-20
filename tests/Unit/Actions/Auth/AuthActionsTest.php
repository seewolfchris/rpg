<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\RegisterUserAction;
use App\Actions\Auth\ResetUserPasswordAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_user_action_creates_user_with_hashed_password(): void
    {
        $user = app(RegisterUserAction::class)->execute([
            'name' => 'Unit User',
            'email' => 'unit-user@example.com',
            'password' => 'plaintext-password',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'unit-user@example.com',
        ]);
        $this->assertTrue(Hash::check('plaintext-password', (string) $user->fresh()?->password));
    }

    public function test_reset_user_password_action_updates_password_and_remember_token(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
            'remember_token' => 'old-token',
        ]);

        app(ResetUserPasswordAction::class)->execute($user, 'new-password-123');

        $fresh = $user->fresh();
        $this->assertNotNull($fresh);
        $this->assertTrue(Hash::check('new-password-123', (string) $fresh->password));
        $this->assertNotSame('old-token', (string) $fresh->remember_token);
    }
}
