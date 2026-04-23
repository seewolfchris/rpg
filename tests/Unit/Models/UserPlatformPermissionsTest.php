<?php

namespace Tests\Unit\Models;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPlatformPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_without_flag_cannot_create_campaigns(): void
    {
        $player = User::factory()->create([
            'can_create_campaigns' => false,
        ]);

        $this->assertFalse($player->canCreateCampaigns());
        $this->assertFalse((bool) $player->can_create_campaigns);
    }

    public function test_player_with_flag_can_create_campaigns(): void
    {
        $player = User::factory()->canCreateCampaigns()->create();

        $this->assertTrue($player->canCreateCampaigns());
        $this->assertTrue((bool) $player->can_create_campaigns);
    }

    public function test_admin_can_create_campaigns_even_without_flag(): void
    {
        $admin = User::factory()->admin()->create([
            'can_create_campaigns' => false,
        ]);

        $this->assertTrue($admin->canCreateCampaigns());
    }

    public function test_user_campaign_memberships_relation_is_available(): void
    {
        $user = User::factory()->create();
        $membership = \App\Models\CampaignMembership::factory()->create([
            'user_id' => $user->id,
        ]);

        $user->load('campaignMemberships');

        $this->assertCount(1, $user->campaignMemberships);
        $this->assertSame((int) $membership->id, (int) $user->campaignMemberships->first()?->id);
    }

    public function test_global_gm_semantics_are_removed_from_role_enum_and_helper(): void
    {
        $player = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->assertNull(UserRole::tryFrom('gm'));
        $this->assertFalse($player->isGmOrAdmin());
        $this->assertTrue($admin->isGmOrAdmin());
    }
}
