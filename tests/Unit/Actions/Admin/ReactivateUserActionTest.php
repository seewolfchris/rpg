<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Admin;

use App\Actions\Admin\ReactivateUserAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReactivateUserActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reactivates_suspended_user_and_sets_approval_if_missing(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->suspended()->create([
            'approved_at' => null,
            'approved_by' => null,
            'suspended_at' => now(),
            'suspended_by' => $admin->id,
            'status_reason' => 'reason',
        ]);

        app(ReactivateUserAction::class)->execute($admin, $target);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'status' => 'active',
            'approved_by' => $admin->id,
            'suspended_at' => null,
            'suspended_by' => null,
            'status_reason' => null,
        ]);
    }

    public function test_it_blocks_reactivation_of_non_suspended_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->active()->create();

        $this->expectException(ValidationException::class);

        app(ReactivateUserAction::class)->execute($admin, $target);
    }
}
