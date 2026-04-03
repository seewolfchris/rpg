<?php

namespace Tests\Feature\MySqlCritical;

use App\Actions\Campaign\UpsertCampaignInvitationAction;
use App\Actions\Campaign\UpsertCampaignInvitationInput;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('mysql-critical')]
class InvitationUpsertMysqlCriticalTest extends TestCase
{
    use RefreshDatabase;

    public function test_upsert_remains_idempotent_for_existing_unique_invitation_pair(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL-only critical test.');
        }

        $owner = User::factory()->gm()->create();
        $invitee = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $invitee->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_DECLINED,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        $action = app(UpsertCampaignInvitationAction::class);

        $resultOne = $action->execute(new UpsertCampaignInvitationInput(
            campaign: $campaign,
            inviteeUserId: (int) $invitee->id,
            inviterUserId: (int) $owner->id,
            requestedRole: CampaignInvitation::ROLE_CO_GM,
        ));

        $resultTwo = $action->execute(new UpsertCampaignInvitationInput(
            campaign: $campaign,
            inviteeUserId: (int) $invitee->id,
            inviterUserId: (int) $owner->id,
            requestedRole: CampaignInvitation::ROLE_CO_GM,
        ));

        $this->assertFalse($resultOne->isNew);
        $this->assertFalse($resultTwo->isNew);

        $this->assertSame(1, CampaignInvitation::query()
            ->where('campaign_id', $campaign->id)
            ->where('user_id', $invitee->id)
            ->count());

        $this->assertDatabaseHas('campaign_invitations', [
            'campaign_id' => $campaign->id,
            'user_id' => $invitee->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_CO_GM,
        ]);
    }
}
