<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
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

    public function test_co_gm_with_active_invitation_can_access_gm_hub(): void
    {
        $owner = User::factory()->gm()->create();
        $coGm = User::factory()->create([
            'role' => UserRole::PLAYER->value,
        ]);
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $coGm->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_CO_GM,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        $response = $this->actingAs($coGm)->get(route('gm.index'));

        $response->assertOk();
    }
}
