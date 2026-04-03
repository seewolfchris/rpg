<?php

namespace Tests\Unit\Actions\Campaign;

use App\Actions\Campaign\UpsertCampaignInvitationAction;
use App\Actions\Campaign\UpsertCampaignInvitationInput;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpsertCampaignInvitationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_new_pending_invitation_when_missing(): void
    {
        $owner = User::factory()->gm()->create();
        $invitee = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        $result = app(UpsertCampaignInvitationAction::class)->execute(new UpsertCampaignInvitationInput(
            campaign: $campaign,
            inviteeUserId: (int) $invitee->id,
            inviterUserId: (int) $owner->id,
            requestedRole: CampaignInvitation::ROLE_PLAYER,
        ));

        $this->assertTrue($result->isNew);
        $this->assertFalse($result->wasAccepted);
        $this->assertDatabaseHas('campaign_invitations', [
            'campaign_id' => $campaign->id,
            'user_id' => $invitee->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_PLAYER,
        ]);
    }

    public function test_it_keeps_accepted_status_when_updating_existing_accepted_invitation(): void
    {
        $owner = User::factory()->gm()->create();
        $invitee = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        $acceptedAt = now()->subDay();
        $respondedAt = now()->subHours(12);
        $existing = CampaignInvitation::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $invitee->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => $acceptedAt,
            'responded_at' => $respondedAt,
            'created_at' => now()->subDays(2),
        ]);

        $result = app(UpsertCampaignInvitationAction::class)->execute(new UpsertCampaignInvitationInput(
            campaign: $campaign,
            inviteeUserId: (int) $invitee->id,
            inviterUserId: (int) $owner->id,
            requestedRole: CampaignInvitation::ROLE_CO_GM,
        ));

        $existing->refresh();

        $this->assertFalse($result->isNew);
        $this->assertTrue($result->wasAccepted);
        $this->assertSame(CampaignInvitation::STATUS_ACCEPTED, $existing->status);
        $this->assertSame(CampaignInvitation::ROLE_CO_GM, $existing->role);
        $this->assertSame($acceptedAt->toDateTimeString(), optional($existing->accepted_at)?->toDateTimeString());
        $this->assertSame($respondedAt->toDateTimeString(), optional($existing->responded_at)?->toDateTimeString());
    }

    public function test_it_resets_declined_invitation_back_to_pending(): void
    {
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
            'accepted_at' => null,
            'responded_at' => now()->subHour(),
            'created_at' => now()->subDays(2),
        ]);

        $result = app(UpsertCampaignInvitationAction::class)->execute(new UpsertCampaignInvitationInput(
            campaign: $campaign,
            inviteeUserId: (int) $invitee->id,
            inviterUserId: (int) $owner->id,
            requestedRole: CampaignInvitation::ROLE_PLAYER,
        ));

        $invitation = CampaignInvitation::query()
            ->where('campaign_id', $campaign->id)
            ->where('user_id', $invitee->id)
            ->firstOrFail();

        $this->assertFalse($result->isNew);
        $this->assertFalse($result->wasAccepted);
        $this->assertSame(CampaignInvitation::STATUS_PENDING, $invitation->status);
        $this->assertNull($invitation->accepted_at);
        $this->assertNull($invitation->responded_at);
    }

    public function test_it_is_idempotent_for_repeated_invitation_upserts(): void
    {
        $owner = User::factory()->gm()->create();
        $invitee = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        app(UpsertCampaignInvitationAction::class)->execute(new UpsertCampaignInvitationInput(
            campaign: $campaign,
            inviteeUserId: (int) $invitee->id,
            inviterUserId: (int) $owner->id,
            requestedRole: CampaignInvitation::ROLE_PLAYER,
        ));

        app(UpsertCampaignInvitationAction::class)->execute(new UpsertCampaignInvitationInput(
            campaign: $campaign,
            inviteeUserId: (int) $invitee->id,
            inviterUserId: (int) $owner->id,
            requestedRole: CampaignInvitation::ROLE_PLAYER,
        ));

        $this->assertSame(
            1,
            CampaignInvitation::query()
                ->where('campaign_id', $campaign->id)
                ->where('user_id', $invitee->id)
                ->count()
        );
    }
}
