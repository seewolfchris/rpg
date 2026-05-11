<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Admin;

use App\Actions\Admin\ApproveUserAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApproveUserActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_approves_pending_user_and_clears_suspension_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->pending()->create([
            'suspended_at' => now(),
            'suspended_by' => $admin->id,
            'status_reason' => 'old reason',
        ]);

        app(ApproveUserAction::class)->execute($admin, $target);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'status' => 'active',
            'approved_by' => $admin->id,
            'suspended_at' => null,
            'suspended_by' => null,
            'status_reason' => null,
        ]);
    }
}
