<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Admin;

use App\Actions\Admin\UpdateAdminManagedUserAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminUserManagementActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_blocks_deactivating_last_active_admin(): void
    {
        $actor = User::factory()->admin()->suspended()->create();
        $target = User::factory()->admin()->active()->create();

        $this->expectException(ValidationException::class);

        app(UpdateAdminManagedUserAction::class)->execute($actor, $target, [
            'name' => $target->name,
            'email' => $target->email,
            'role' => UserRole::ADMIN->value,
            'status' => UserStatus::SUSPENDED->value,
            'can_create_campaigns' => (bool) $target->can_create_campaigns,
            'can_post_without_moderation' => (bool) $target->can_post_without_moderation,
            'status_reason' => 'last active admin',
        ]);
    }
}
