<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserModerationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_promote_user_to_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create([
            'role' => UserRole::PLAYER->value,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.moderation.update', ['user' => $user]), [
                'role' => UserRole::ADMIN->value,
                'can_create_campaigns' => '0',
                'can_post_without_moderation' => '0',
            ])
            ->assertRedirect(route('admin.users.moderation.index', ['q' => null]));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => UserRole::ADMIN->value,
        ]);
    }

    public function test_admin_can_demote_admin_to_user_except_last_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.moderation.update', ['user' => $otherAdmin]), [
                'role' => UserRole::PLAYER->value,
                'can_create_campaigns' => '1',
                'can_post_without_moderation' => '1',
            ])
            ->assertRedirect(route('admin.users.moderation.index', ['q' => null]));

        $this->assertDatabaseHas('users', [
            'id' => $otherAdmin->id,
            'role' => UserRole::PLAYER->value,
            'can_create_campaigns' => true,
            'can_post_without_moderation' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.users.moderation.index'))
            ->patch(route('admin.users.moderation.update', ['user' => $admin]), [
                'role' => UserRole::PLAYER->value,
                'can_create_campaigns' => '0',
                'can_post_without_moderation' => '0',
            ])
            ->assertRedirect(route('admin.users.moderation.index'))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => UserRole::ADMIN->value,
        ]);
    }

    public function test_admin_cannot_demote_self_from_admin(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.moderation.index'))
            ->patch(route('admin.users.moderation.update', ['user' => $admin]), [
                'role' => UserRole::PLAYER->value,
                'can_create_campaigns' => '0',
                'can_post_without_moderation' => '0',
            ])
            ->assertRedirect(route('admin.users.moderation.index'))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => UserRole::ADMIN->value,
        ]);
    }

    public function test_admin_can_toggle_can_create_campaigns(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create([
            'can_create_campaigns' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.moderation.update', ['user' => $user]), [
                'role' => UserRole::PLAYER->value,
                'can_create_campaigns' => '1',
                'can_post_without_moderation' => '0',
            ])
            ->assertRedirect(route('admin.users.moderation.index', ['q' => null]));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'can_create_campaigns' => true,
        ]);
    }

    public function test_admin_can_toggle_can_post_without_moderation(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create([
            'can_post_without_moderation' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.moderation.update', ['user' => $user]), [
                'role' => UserRole::PLAYER->value,
                'can_create_campaigns' => '0',
                'can_post_without_moderation' => '1',
            ])
            ->assertRedirect(route('admin.users.moderation.index', ['q' => null]));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'can_post_without_moderation' => true,
        ]);
    }

    public function test_non_admin_cannot_access_or_mutate_platform_rights_ui(): void
    {
        $player = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($player)
            ->get(route('admin.users.moderation.index'))
            ->assertForbidden();

        $this->actingAs($player)
            ->patch(route('admin.users.moderation.update', ['user' => $target]), [
                'role' => UserRole::PLAYER->value,
                'can_create_campaigns' => '1',
                'can_post_without_moderation' => '1',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role' => UserRole::PLAYER->value,
            'can_create_campaigns' => false,
            'can_post_without_moderation' => false,
        ]);
    }
}
