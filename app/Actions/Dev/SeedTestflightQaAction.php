<?php

namespace App\Actions\Dev;

use App\Actions\Campaign\UpsertCampaignInvitationAction;
use App\Actions\Campaign\UpsertCampaignInvitationInput;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;

class SeedTestflightQaAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly UpsertCampaignInvitationAction $upsertCampaignInvitationAction,
    ) {}

    /**
     * @return array{
     *     world: World,
     *     campaign: Campaign,
     *     scene: Scene,
     *     accounts: array{
     *         gm: User,
     *         co_gm: User,
     *         player_one: User,
     *         player_two: User,
     *         trusted_player: User
     *     }
     * }
     */
    public function execute(World $world, string $campaignSlug, string $plainPassword): array
    {
        /** @var array{
         *     world: World,
         *     campaign: Campaign,
         *     scene: Scene,
         *     accounts: array{
         *         gm: User,
         *         co_gm: User,
         *         player_one: User,
         *         player_two: User,
         *         trusted_player: User
         *     }
         * } $result */
        $result = $this->db->transaction(function () use ($world, $campaignSlug, $plainPassword): array {
            $accounts = $this->upsertAccounts(
                worldSlug: $world->slug,
                plainPassword: $plainPassword,
            );

            $campaign = Campaign::query()->updateOrCreate(
                ['slug' => $campaignSlug],
                [
                    'world_id' => (int) $world->id,
                    'owner_id' => (int) $accounts['gm']->id,
                    'title' => '[TESTFLIGHT] QA-Kampagne · '.$world->name,
                    'summary' => '[TESTFLIGHT] Reproduzierbare Kampagne für Invite-, Rollen- und Posting-Checks.',
                    'lore' => '[TESTFLIGHT] Diese Daten werden von dev:testflight:seed auf Soll-Zustand gehalten.',
                    'is_public' => false,
                    'requires_post_moderation' => true,
                    'status' => 'active',
                    'starts_at' => null,
                    'ends_at' => null,
                ],
            );

            $scene = Scene::query()->updateOrCreate(
                [
                    'campaign_id' => (int) $campaign->id,
                    'slug' => 'testflight-hub',
                ],
                [
                    'created_by' => (int) $accounts['gm']->id,
                    'title' => '[TESTFLIGHT] QA-Hub',
                    'previous_scene_id' => null,
                    'summary' => '[TESTFLIGHT] Startszene für manuelle QA-Läufe.',
                    'description' => '[TESTFLIGHT] Nutze diese Szene für Posting-, Moderations- und Invite-Checks.',
                    'header_image_path' => null,
                    'status' => 'open',
                    'mood' => 'neutral',
                    'position' => 1,
                    'allow_ooc' => true,
                    'opens_at' => null,
                    'closes_at' => null,
                ],
            );

            $this->syncInvitation(
                campaign: $campaign,
                invitee: $accounts['co_gm'],
                inviter: $accounts['gm'],
                role: CampaignInvitation::ROLE_CO_GM,
                status: CampaignInvitation::STATUS_ACCEPTED,
            );

            $this->syncInvitation(
                campaign: $campaign,
                invitee: $accounts['player_one'],
                inviter: $accounts['gm'],
                role: CampaignInvitation::ROLE_PLAYER,
                status: CampaignInvitation::STATUS_ACCEPTED,
            );

            $this->syncInvitation(
                campaign: $campaign,
                invitee: $accounts['player_two'],
                inviter: $accounts['gm'],
                role: CampaignInvitation::ROLE_PLAYER,
                status: CampaignInvitation::STATUS_PENDING,
            );

            $this->syncInvitation(
                campaign: $campaign,
                invitee: $accounts['trusted_player'],
                inviter: $accounts['gm'],
                role: CampaignInvitation::ROLE_TRUSTED_PLAYER,
                status: CampaignInvitation::STATUS_ACCEPTED,
            );

            return [
                'world' => $world,
                'campaign' => $campaign,
                'scene' => $scene,
                'accounts' => $accounts,
            ];
        }, 3);

        return $result;
    }

    /**
     * @return array{
     *     gm: User,
     *     co_gm: User,
     *     player_one: User,
     *     player_two: User,
     *     trusted_player: User
     * }
     */
    private function upsertAccounts(string $worldSlug, string $plainPassword): array
    {
        return [
            'gm' => $this->upsertAccount(
                key: 'gm',
                worldSlug: $worldSlug,
                name: '[TESTFLIGHT] Spielleitung '.$worldSlug,
                role: UserRole::GM,
                plainPassword: $plainPassword,
            ),
            'co_gm' => $this->upsertAccount(
                key: 'co_gm',
                worldSlug: $worldSlug,
                name: '[TESTFLIGHT] Co-GM '.$worldSlug,
                role: UserRole::PLAYER,
                plainPassword: $plainPassword,
            ),
            'player_one' => $this->upsertAccount(
                key: 'player_one',
                worldSlug: $worldSlug,
                name: '[TESTFLIGHT] Spieler Eins '.$worldSlug,
                role: UserRole::PLAYER,
                plainPassword: $plainPassword,
            ),
            'player_two' => $this->upsertAccount(
                key: 'player_two',
                worldSlug: $worldSlug,
                name: '[TESTFLIGHT] Spieler Zwei '.$worldSlug,
                role: UserRole::PLAYER,
                plainPassword: $plainPassword,
            ),
            'trusted_player' => $this->upsertAccount(
                key: 'trusted_player',
                worldSlug: $worldSlug,
                name: '[TESTFLIGHT] Trusted Player '.$worldSlug,
                role: UserRole::PLAYER,
                plainPassword: $plainPassword,
            ),
        ];
    }

    private function upsertAccount(
        string $key,
        string $worldSlug,
        string $name,
        UserRole $role,
        string $plainPassword,
    ): User {
        $user = User::query()->firstOrNew([
            'email' => $this->accountEmail($key, $worldSlug),
        ]);

        $user->forceFill([
            'name' => $name,
            'role' => $role->value,
            'password' => $plainPassword,
            'email_verified_at' => now(),
            'can_post_without_moderation' => false,
            'offline_queue_enabled' => true,
        ]);
        $user->save();

        return $user;
    }

    private function accountEmail(string $key, string $worldSlug): string
    {
        $normalizedKey = str_replace('_', '-', $key);

        return sprintf('testflight.%s+%s@example.test', $normalizedKey, $worldSlug);
    }

    private function syncInvitation(
        Campaign $campaign,
        User $invitee,
        User $inviter,
        string $role,
        string $status,
    ): void {
        if (! in_array($status, [CampaignInvitation::STATUS_PENDING, CampaignInvitation::STATUS_ACCEPTED], true)) {
            throw new InvalidArgumentException('Unsupported invitation status for testflight seeding: '.$status);
        }

        $result = $this->upsertCampaignInvitationAction->execute(
            new UpsertCampaignInvitationInput(
                campaign: $campaign,
                inviteeUserId: (int) $invitee->id,
                inviterUserId: (int) $inviter->id,
                requestedRole: $role,
            ),
        );

        $invitation = $result->invitation;
        $invitation->role = $role;
        $invitation->status = $status;
        $invitation->invited_by = (int) $inviter->id;

        if ($status === CampaignInvitation::STATUS_ACCEPTED) {
            $acceptedAt = $invitation->accepted_at ?? now();
            $invitation->accepted_at = $acceptedAt;
            $invitation->responded_at = $invitation->responded_at ?? $acceptedAt;
        } else {
            $invitation->accepted_at = null;
            $invitation->responded_at = null;
        }

        $invitation->save();
    }
}
