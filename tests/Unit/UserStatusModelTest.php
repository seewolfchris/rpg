<?php

namespace Tests\Unit;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStatusModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_factory_defaults_to_active_status(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserStatus::ACTIVE, $user->status);
        $this->assertTrue($user->isActive());
        $this->assertTrue($user->canAccessPlatform());
    }

    public function test_user_factory_status_states_work_as_expected(): void
    {
        $pendingUser = User::factory()->pending()->create();
        $activeUser = User::factory()->active()->create();
        $suspendedUser = User::factory()->suspended()->create();

        $this->assertTrue($pendingUser->isPending());
        $this->assertFalse($pendingUser->canAccessPlatform());

        $this->assertTrue($activeUser->isActive());
        $this->assertTrue($activeUser->canAccessPlatform());

        $this->assertTrue($suspendedUser->isSuspended());
        $this->assertFalse($suspendedUser->canAccessPlatform());
    }
}
