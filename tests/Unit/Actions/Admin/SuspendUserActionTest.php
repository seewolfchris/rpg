<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Admin;

use App\Actions\Admin\SuspendUserAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SuspendUserActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_suspends_active_user_with_optional_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->active()->create();

        app(SuspendUserAction::class)->execute($admin, $target, 'Policy violation');

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'status' => 'suspended',
            'suspended_by' => $admin->id,
            'status_reason' => 'Policy violation',
        ]);
    }

    public function test_it_blocks_self_suspension(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();

        $this->expectException(ValidationException::class);

        app(SuspendUserAction::class)->execute($admin, $admin, 'self block');
    }

    public function test_it_blocks_suspending_last_active_admin(): void
    {
        $actor = User::factory()->admin()->suspended()->create();
        $target = User::factory()->admin()->active()->create();

        $this->expectException(ValidationException::class);

        app(SuspendUserAction::class)->execute($actor, $target, 'last active admin');
    }
}
