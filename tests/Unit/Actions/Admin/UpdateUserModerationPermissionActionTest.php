<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Admin;

use App\Actions\Admin\UpdateUserModerationPermissionAction;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UpdateUserModerationPermissionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_platform_rights_for_target_user(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create([
            'role' => UserRole::PLAYER->value,
            'can_create_campaigns' => false,
            'can_post_without_moderation' => false,
        ]);

        app(UpdateUserModerationPermissionAction::class)->execute($actor, $target, [
            'role' => UserRole::ADMIN->value,
            'can_create_campaigns' => true,
            'can_post_without_moderation' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role' => UserRole::ADMIN->value,
            'can_create_campaigns' => true,
            'can_post_without_moderation' => true,
        ]);
    }

    public function test_it_blocks_self_admin_demotion_even_when_other_admins_exist(): void
    {
        $actor = User::factory()->admin()->create();
        User::factory()->admin()->create();

        $this->expectException(ValidationException::class);

        app(UpdateUserModerationPermissionAction::class)->execute($actor, $actor, [
            'role' => UserRole::PLAYER->value,
            'can_create_campaigns' => false,
            'can_post_without_moderation' => false,
        ]);
    }

    public function test_it_blocks_last_admin_demotion(): void
    {
        $actor = User::factory()->admin()->create();

        $this->expectException(ValidationException::class);

        app(UpdateUserModerationPermissionAction::class)->execute($actor, $actor, [
            'role' => UserRole::PLAYER->value,
            'can_create_campaigns' => false,
            'can_post_without_moderation' => false,
        ]);
    }
}
