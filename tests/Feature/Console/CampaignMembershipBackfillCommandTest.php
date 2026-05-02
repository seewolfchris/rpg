<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\CampaignMembership;
use App\Models\CampaignRoleEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignMembershipBackfillCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_changes_without_persisting_membership_mutations(): void
    {
        [$campaign, $owner] = $this->seedCampaign();

        $createUser = User::factory()->create();
        $updateUser = User::factory()->create();
        $unchangedUser = User::factory()->create();

        CampaignMembership::query()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $updateUser->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => (int) $owner->id,
            'assigned_at' => now(),
        ]);
        CampaignMembership::query()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $unchangedUser->id,
            'role' => CampaignMembershipRole::GM->value,
            'assigned_by' => (int) $owner->id,
            'assigned_at' => now(),
        ]);

        $this->acceptedInvitation($campaign, $owner, $createUser, CampaignInvitation::ROLE_PLAYER);
        $this->acceptedInvitation($campaign, $owner, $updateUser, CampaignInvitation::ROLE_TRUSTED_PLAYER);
        $this->acceptedInvitation($campaign, $owner, $unchangedUser, CampaignInvitation::ROLE_CO_GM);
        $this->pendingInvitation($campaign, $owner, User::factory()->create(), CampaignInvitation::ROLE_PLAYER);

        $this->artisan('campaigns:backfill-memberships-from-invitations --dry-run')
            ->expectsOutputToContain('mode: dry-run')
            ->expectsOutputToContain('accepted_invitations: 3')
            ->expectsOutputToContain('memberships_created: 1')
            ->expectsOutputToContain('memberships_updated: 1')
            ->expectsOutputToContain('memberships_unchanged: 1')
            ->expectsOutputToContain('skipped_errors: 0')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('campaign_memberships', [
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $createUser->id,
        ]);
        $this->assertDatabaseHas('campaign_memberships', [
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $updateUser->id,
            'role' => CampaignMembershipRole::PLAYER->value,
        ]);
        $this->assertDatabaseHas('campaign_memberships', [
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $unchangedUser->id,
            'role' => CampaignMembershipRole::GM->value,
        ]);
        $this->assertSame(0, CampaignRoleEvent::query()->count());
    }

    public function test_apply_mode_is_idempotent_and_uses_same_report_contract(): void
    {
        [$campaign, $owner] = $this->seedCampaign();

        $createUser = User::factory()->create();
        $updateUser = User::factory()->create();
        $unchangedUser = User::factory()->create();

        CampaignMembership::query()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $updateUser->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => (int) $owner->id,
            'assigned_at' => now(),
        ]);
        CampaignMembership::query()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $unchangedUser->id,
            'role' => CampaignMembershipRole::GM->value,
            'assigned_by' => (int) $owner->id,
            'assigned_at' => now(),
        ]);

        $this->acceptedInvitation($campaign, $owner, $createUser, CampaignInvitation::ROLE_PLAYER);
        $this->acceptedInvitation($campaign, $owner, $updateUser, CampaignInvitation::ROLE_TRUSTED_PLAYER);
        $this->acceptedInvitation($campaign, $owner, $unchangedUser, CampaignInvitation::ROLE_CO_GM);

        $this->artisan('campaigns:backfill-memberships-from-invitations')
            ->expectsOutputToContain('mode: apply')
            ->expectsOutputToContain('accepted_invitations: 3')
            ->expectsOutputToContain('memberships_created: 1')
            ->expectsOutputToContain('memberships_updated: 1')
            ->expectsOutputToContain('memberships_unchanged: 1')
            ->expectsOutputToContain('skipped_errors: 0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('campaign_memberships', [
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $createUser->id,
            'role' => CampaignMembershipRole::PLAYER->value,
        ]);
        $this->assertDatabaseHas('campaign_memberships', [
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $updateUser->id,
            'role' => CampaignMembershipRole::TRUSTED_PLAYER->value,
        ]);
        $this->assertDatabaseHas('campaign_memberships', [
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $unchangedUser->id,
            'role' => CampaignMembershipRole::GM->value,
        ]);

        $this->artisan('campaigns:backfill-memberships-from-invitations')
            ->expectsOutputToContain('mode: apply')
            ->expectsOutputToContain('accepted_invitations: 3')
            ->expectsOutputToContain('memberships_created: 0')
            ->expectsOutputToContain('memberships_updated: 0')
            ->expectsOutputToContain('memberships_unchanged: 3')
            ->expectsOutputToContain('skipped_errors: 0')
            ->assertExitCode(0);
    }

    /**
     * @return array{0: Campaign, 1: User}
     */
    private function seedCampaign(): array
    {
        $owner = User::factory()->gm()->create();
        $campaign = Campaign::factory()->create([
            'owner_id' => (int) $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        return [$campaign, $owner];
    }

    private function acceptedInvitation(Campaign $campaign, User $owner, User $user, string $role): void
    {
        CampaignInvitation::withoutEvents(function () use ($campaign, $owner, $user, $role): void {
            CampaignInvitation::query()->create([
                'campaign_id' => (int) $campaign->id,
                'user_id' => (int) $user->id,
                'invited_by' => (int) $owner->id,
                'status' => CampaignInvitation::STATUS_ACCEPTED,
                'role' => $role,
                'accepted_at' => now(),
                'responded_at' => now(),
                'created_at' => now(),
            ]);
        });
    }

    private function pendingInvitation(Campaign $campaign, User $owner, User $user, string $role): void
    {
        CampaignInvitation::withoutEvents(function () use ($campaign, $owner, $user, $role): void {
            CampaignInvitation::query()->create([
                'campaign_id' => (int) $campaign->id,
                'user_id' => (int) $user->id,
                'invited_by' => (int) $owner->id,
                'status' => CampaignInvitation::STATUS_PENDING,
                'role' => $role,
                'accepted_at' => null,
                'responded_at' => null,
                'created_at' => now(),
            ]);
        });
    }
}
