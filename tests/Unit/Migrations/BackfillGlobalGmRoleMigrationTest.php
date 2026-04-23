<?php

namespace Tests\Unit\Migrations;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackfillGlobalGmRoleMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_migrates_global_gm_users_to_player_with_create_flag_and_is_idempotent(): void
    {
        $legacyGlobalGm = User::factory()->create([
            'role' => UserRole::PLAYER->value,
            'can_create_campaigns' => false,
        ]);

        DB::table('users')
            ->where('id', (int) $legacyGlobalGm->id)
            ->update([
                'role' => 'gm',
                'can_create_campaigns' => false,
            ]);

        $admin = User::factory()->admin()->create([
            'can_create_campaigns' => false,
        ]);

        /** @var \Illuminate\Database\Migrations\Migration $migration */
        $migration = require database_path('migrations/2026_04_23_090300_backfill_global_gm_role_to_player.php');

        $migration->up();
        $migration->up();

        $legacyRow = DB::table('users')
            ->where('id', (int) $legacyGlobalGm->id)
            ->first(['role', 'can_create_campaigns']);
        $adminRow = DB::table('users')
            ->where('id', (int) $admin->id)
            ->first(['role', 'can_create_campaigns']);

        $this->assertNotNull($legacyRow);
        $this->assertNotNull($adminRow);

        $this->assertSame(UserRole::PLAYER->value, (string) $legacyRow->role);
        $this->assertSame(1, (int) $legacyRow->can_create_campaigns);

        $this->assertSame(UserRole::ADMIN->value, (string) $adminRow->role);
        $this->assertSame(0, (int) $adminRow->can_create_campaigns);
    }
}
