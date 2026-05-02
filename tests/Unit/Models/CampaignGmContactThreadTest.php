<?php

namespace Tests\Unit\Models;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignGmContactThread;
use App\Models\CampaignMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignGmContactThreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_visible_to_scope_is_always_hard_scoped_to_target_campaign(): void
    {
        $ownerA = User::factory()->gm()->create();
        $ownerB = User::factory()->gm()->create();
        $coGmA = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $playerA = User::factory()->create();
        $playerB = User::factory()->create();

        $campaignA = Campaign::factory()->create([
            'owner_id' => $ownerA->id,
            'is_public' => false,
            'status' => 'active',
        ]);
        $campaignB = Campaign::factory()->create([
            'owner_id' => $ownerB->id,
            'is_public' => false,
            'status' => 'active',
        ]);

        $this->grantMembership($campaignA, $coGmA, CampaignMembershipRole::GM, $ownerA);
        $this->grantMembership($campaignA, $playerA, CampaignMembershipRole::PLAYER, $ownerA);
        $this->grantMembership($campaignB, $playerB, CampaignMembershipRole::PLAYER, $ownerB);

        $threadA = CampaignGmContactThread::factory()->create([
            'campaign_id' => $campaignA->id,
            'created_by' => $playerA->id,
            'subject' => 'Sichtbar nur in A',
        ]);
        $threadB = CampaignGmContactThread::factory()->create([
            'campaign_id' => $campaignB->id,
            'created_by' => $playerB->id,
            'subject' => 'Sichtbar nur in B',
        ]);

        $ownerIds = CampaignGmContactThread::query()
            ->visibleTo($ownerA, $campaignA)
            ->pluck('id')
            ->all();
        $coGmIds = CampaignGmContactThread::query()
            ->visibleTo($coGmA, $campaignA)
            ->pluck('id')
            ->all();
        $adminIds = CampaignGmContactThread::query()
            ->visibleTo($admin, $campaignA)
            ->pluck('id')
            ->all();

        $this->assertSame([$threadA->id], $ownerIds);
        $this->assertSame([$threadA->id], $coGmIds);
        $this->assertSame([$threadA->id], $adminIds);
        $this->assertNotContains($threadB->id, $ownerIds);
        $this->assertNotContains($threadB->id, $coGmIds);
        $this->assertNotContains($threadB->id, $adminIds);
    }

    public function test_status_label_mapping_returns_expected_labels(): void
    {
        $this->assertSame('Offen', CampaignGmContactThread::statusLabelFor(CampaignGmContactThread::STATUS_OPEN));
        $this->assertSame('Wartet auf Spielleitung', CampaignGmContactThread::statusLabelFor(CampaignGmContactThread::STATUS_WAITING_FOR_GM));
        $this->assertSame('Wartet auf Spieler', CampaignGmContactThread::statusLabelFor(CampaignGmContactThread::STATUS_WAITING_FOR_PLAYER));
        $this->assertSame('Geschlossen', CampaignGmContactThread::statusLabelFor(CampaignGmContactThread::STATUS_CLOSED));

        $thread = new CampaignGmContactThread([
            'status' => CampaignGmContactThread::STATUS_WAITING_FOR_GM,
        ]);

        $this->assertSame('Wartet auf Spielleitung', $thread->statusLabel());
        $this->assertSame('foo', CampaignGmContactThread::statusLabelFor('foo'));
    }

    private function grantMembership(
        Campaign $campaign,
        User $member,
        CampaignMembershipRole $role,
        User $assigner
    ): void
    {
        CampaignMembership::query()->updateOrCreate(
            [
                'campaign_id' => (int) $campaign->id,
                'user_id' => (int) $member->id,
            ],
            [
                'role' => $role->value,
                'assigned_by' => (int) $assigner->id,
                'assigned_at' => now(),
            ],
        );
    }
}
