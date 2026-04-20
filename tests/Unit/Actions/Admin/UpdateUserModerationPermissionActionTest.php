<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Admin;

use App\Actions\Admin\UpdateUserModerationPermissionAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UpdateUserModerationPermissionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_enables_permission_for_player(): void
    {
        $player = User::factory()->create([
            'can_post_without_moderation' => false,
        ]);

        app(UpdateUserModerationPermissionAction::class)->execute($player, true);

        $this->assertDatabaseHas('users', [
            'id' => $player->id,
            'can_post_without_moderation' => true,
        ]);
    }

    public function test_it_throws_when_enabling_permission_for_non_player_role(): void
    {
        $gm = User::factory()->gm()->create([
            'can_post_without_moderation' => false,
        ]);

        $this->expectException(ValidationException::class);

        app(UpdateUserModerationPermissionAction::class)->execute($gm, true);
    }

    public function test_it_forces_permission_false_for_non_player_roles(): void
    {
        $gm = User::factory()->gm()->create([
            'can_post_without_moderation' => true,
        ]);

        app(UpdateUserModerationPermissionAction::class)->execute($gm, false);

        $this->assertDatabaseHas('users', [
            'id' => $gm->id,
            'can_post_without_moderation' => false,
        ]);
    }
}
