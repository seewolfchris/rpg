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

        $this->actingAs($player)
            ->patch(route('admin.users.moderation.suspend', ['user' => $target]), [
                'status_reason' => 'non-admin attempt',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role' => UserRole::PLAYER->value,
            'can_create_campaigns' => false,
            'can_post_without_moderation' => false,
        ]);
    }

    public function test_admin_can_approve_pending_user(): void
    {
        $admin = User::factory()->admin()->create();
        $pendingUser = User::factory()->pending()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.moderation.approve', ['user' => $pendingUser]))
            ->assertRedirect(route('admin.users.moderation.index', ['q' => null]));

        $this->assertDatabaseHas('users', [
            'id' => $pendingUser->id,
            'status' => 'active',
            'approved_by' => $admin->id,
            'suspended_at' => null,
            'suspended_by' => null,
        ]);
    }

    public function test_admin_can_suspend_active_user(): void
    {
        $admin = User::factory()->admin()->create();
        $activeUser = User::factory()->active()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.moderation.suspend', ['user' => $activeUser]), [
                'status_reason' => 'Regelverstoß',
            ])
            ->assertRedirect(route('admin.users.moderation.index', ['q' => null]));

        $this->assertDatabaseHas('users', [
            'id' => $activeUser->id,
            'status' => 'suspended',
            'suspended_by' => $admin->id,
            'status_reason' => 'Regelverstoß',
        ]);
    }

    public function test_admin_can_reactivate_suspended_user(): void
    {
        $admin = User::factory()->admin()->create();
        $suspendedUser = User::factory()->suspended()->create([
            'suspended_at' => now(),
            'suspended_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.moderation.reactivate', ['user' => $suspendedUser]))
            ->assertRedirect(route('admin.users.moderation.index', ['q' => null]));

        $this->assertDatabaseHas('users', [
            'id' => $suspendedUser->id,
            'status' => 'active',
            'suspended_at' => null,
            'suspended_by' => null,
            'status_reason' => null,
        ]);
    }

    public function test_admin_cannot_suspend_self(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.moderation.index'))
            ->patch(route('admin.users.moderation.suspend', ['user' => $admin]), [
                'status_reason' => 'self suspend',
            ])
            ->assertRedirect(route('admin.users.moderation.index'))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'status' => 'active',
        ]);
    }
}
