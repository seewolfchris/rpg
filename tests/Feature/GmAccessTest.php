<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GmAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_cannot_access_gm_hub(): void
    {
        $player = User::factory()->create([
            'role' => UserRole::PLAYER->value,
        ]);

        $response = $this->actingAs($player)->get(route('gm.index'));

        $response->assertForbidden();
    }

    public function test_gm_can_access_gm_hub(): void
    {
        $gm = User::factory()->gm()->create();

        $response = $this->actingAs($gm)->get(route('gm.index'));

        $response->assertOk();
    }

    public function test_admin_can_access_gm_hub(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('gm.index'));

        $response->assertOk();
    }
}
