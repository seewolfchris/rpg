<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserModerationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_toggle_player_post_without_moderation_permission(): void
    {
        $admin = User::factory()->admin()->create();
        $player = User::factory()->create([
            'can_post_without_moderation' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.moderation.update', ['user' => $player, 'q' => 'spieler']), [
                'can_post_without_moderation' => '1',
            ])
            ->assertRedirect(route('admin.users.moderation.index', ['q' => 'spieler']));

        $this->assertDatabaseHas('users', [
            'id' => $player->id,
            'can_post_without_moderation' => true,
        ]);
    }

    public function test_admin_cannot_enable_permission_for_non_player_roles(): void
    {
        $admin = User::factory()->admin()->create();
        $gm = User::factory()->gm()->create([
            'can_post_without_moderation' => false,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.users.moderation.index'))
            ->patch(route('admin.users.moderation.update', $gm), [
                'can_post_without_moderation' => '1',
            ])
            ->assertRedirect(route('admin.users.moderation.index'))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', [
            'id' => $gm->id,
            'can_post_without_moderation' => false,
        ]);
    }

    public function test_non_admin_cannot_access_admin_user_moderation_routes(): void
    {
        $gm = User::factory()->gm()->create();
        $player = User::factory()->create();

        $this->actingAs($gm)
            ->get(route('admin.users.moderation.index'))
            ->assertForbidden();

        $this->actingAs($gm)
            ->patch(route('admin.users.moderation.update', $player), [
                'can_post_without_moderation' => '1',
            ])
            ->assertForbidden();
    }
}
