<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_user_overview(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create([
            'name' => 'User Overview Target',
            'email' => 'overview-target@example.test',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Benutzerverwaltung')
            ->assertSee($target->name)
            ->assertSee($target->email)
            ->assertSee('Benutzer erstellen');
    }

    public function test_non_admin_gets_403_for_user_overview(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_user_without_false_terms_acceptance(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Admin Created User',
                'email' => 'admin-created@example.test',
                'password' => 'AdminCreatedPassword123!',
                'password_confirmation' => 'AdminCreatedPassword123!',
                'role' => UserRole::PLAYER->value,
                'status' => UserStatus::PENDING->value,
                'can_create_campaigns' => '1',
                'can_post_without_moderation' => '0',
            ])
            ->assertRedirect();

        $created = User::query()->where('email', 'admin-created@example.test')->firstOrFail();

        $this->assertSame('Admin Created User', $created->name);
        $this->assertTrue(Hash::check('AdminCreatedPassword123!', (string) $created->password));
        $this->assertSame(UserRole::PLAYER, $created->role);
        $this->assertSame(UserStatus::PENDING, $created->status);
        $this->assertTrue((bool) $created->can_create_campaigns);
        $this->assertFalse((bool) $created->can_post_without_moderation);
        $this->assertNull($created->terms_accepted_at);
        $this->assertNull($created->terms_version);
    }

    public function test_admin_can_change_name_and_email(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old-name@example.test',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $target), $this->updatePayload($target, [
                'name' => 'New Name',
                'email' => 'new-name@example.test',
            ]))
            ->assertRedirect(route('admin.users.show', $target));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => 'New Name',
            'email' => 'new-name@example.test',
        ]);
    }

    public function test_admin_can_reset_password(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $target), $this->updatePayload($target, [
                'password' => 'ReplacementPassword123!',
                'password_confirmation' => 'ReplacementPassword123!',
            ]))
            ->assertRedirect(route('admin.users.show', $target));

        $target->refresh();

        $this->assertTrue(Hash::check('ReplacementPassword123!', (string) $target->password));
    }

    public function test_admin_can_change_platform_role(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create([
            'role' => UserRole::PLAYER->value,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $target), $this->updatePayload($target, [
                'role' => UserRole::ADMIN->value,
            ]))
            ->assertRedirect(route('admin.users.show', $target));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role' => UserRole::ADMIN->value,
        ]);
    }

    public function test_admin_can_change_sl_right(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create([
            'can_create_campaigns' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $target), $this->updatePayload($target, [
                'can_create_campaigns' => '1',
            ]))
            ->assertRedirect(route('admin.users.show', $target));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'can_create_campaigns' => true,
        ]);
    }

    public function test_admin_can_change_moderation_right(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create([
            'can_post_without_moderation' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $target), $this->updatePayload($target, [
                'can_post_without_moderation' => '1',
            ]))
            ->assertRedirect(route('admin.users.show', $target));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'can_post_without_moderation' => true,
        ]);
    }

    public function test_admin_can_change_status(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->active()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $target), $this->updatePayload($target, [
                'status' => UserStatus::SUSPENDED->value,
                'status_reason' => 'Admin status change',
            ]))
            ->assertRedirect(route('admin.users.show', $target));

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'status' => UserStatus::SUSPENDED->value,
            'suspended_by' => $admin->id,
            'status_reason' => 'Admin status change',
        ]);
    }

    public function test_last_admin_remains_protected(): void
    {
        $admin = User::factory()->admin()->active()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.edit', $admin))
            ->patch(route('admin.users.update', $admin), $this->updatePayload($admin, [
                'role' => UserRole::PLAYER->value,
            ]))
            ->assertRedirect(route('admin.users.edit', $admin))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => UserRole::ADMIN->value,
            'status' => UserStatus::ACTIVE->value,
        ]);
    }

    public function test_admin_cannot_demote_self_even_when_other_admin_exists(): void
    {
        $admin = User::factory()->admin()->active()->create();
        User::factory()->admin()->active()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.edit', $admin))
            ->patch(route('admin.users.update', $admin), $this->updatePayload($admin, [
                'role' => UserRole::PLAYER->value,
            ]))
            ->assertRedirect(route('admin.users.edit', $admin))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => UserRole::ADMIN->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function updatePayload(User $user, array $overrides = []): array
    {
        $role = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
        $status = $user->status instanceof UserStatus ? $user->status->value : (string) $user->status;

        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role,
            'status' => $status,
            'can_create_campaigns' => $user->can_create_campaigns ? '1' : '0',
            'can_post_without_moderation' => $user->can_post_without_moderation ? '1' : '0',
            'status_reason' => (string) $user->status_reason,
        ], $overrides);
    }
}
