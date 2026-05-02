<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\CampaignGmContact\NotifyCampaignGmContactMessageAction;
use App\Actions\Post\BuildPostThreadItemFragmentAction;
use App\Domain\Campaign\CampaignParticipantResolver;
use App\Domain\Post\ScenePostNotificationService;
use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignGmContactThread;
use App\Models\CampaignInvitation;
use App\Models\CampaignMembership;
use App\Models\Character;
use App\Models\Post;
use App\Models\Scene;
use App\Models\SceneBookmark;
use App\Models\SceneSubscription;
use App\Models\User;
use App\Notifications\CampaignGmContactMessageNotification;
use App\Notifications\SceneNewPostNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    public static function matrixContexts(): array
    {
        return [
            'private_membership' => [false, 'membership'],
            'public_membership' => [true, 'membership'],
            'private_legacy_invitation' => [false, 'legacy_invitation'],
            'public_legacy_invitation' => [true, 'legacy_invitation'],
        ];
    }

    #[DataProvider('matrixContexts')]
    public function test_access_matrix_campaign_visibility_and_permissions(bool $isPublic, string $accessSource): void
    {
        $actors = $this->seedActors();
        $campaign = $this->seedCampaign($actors['owner'], $isPublic);
        $this->grantCampaignAccessBySource($campaign, $actors, $accessSource);

        /** @var CampaignParticipantResolver $participantResolver */
        $participantResolver = app(CampaignParticipantResolver::class);
        $participantUserIds = $participantResolver->participantUserIds($campaign);

        foreach (['owner', 'gm', 'trusted_player', 'player', 'outsider', 'admin'] as $role) {
            $user = $actors[$role];

            $this->assertSame(
                $this->expectedCampaignVisible($role, $isPublic, $accessSource),
                $campaign->isVisibleTo($user),
                "visibility mismatch for role={$role}, source={$accessSource}, public=".($isPublic ? '1' : '0')
            );

            $this->assertSame(
                $this->expectedCanManage($role, $accessSource),
                $campaign->canManageCampaign($user),
                "canManage mismatch for role={$role}, source={$accessSource}, public=".($isPublic ? '1' : '0')
            );

            $this->assertSame(
                $this->expectedCanManage($role, $accessSource),
                $campaign->canModeratePosts($user),
                "canModerate mismatch for role={$role}, source={$accessSource}, public=".($isPublic ? '1' : '0')
            );

            $this->assertSame(
                $this->expectedIsParticipant($role, $accessSource),
                $participantResolver->isParticipantUserId($campaign, (int) $user->id, $participantUserIds),
                "participant mismatch for role={$role}, source={$accessSource}, public=".($isPublic ? '1' : '0')
            );
        }
    }

    #[DataProvider('matrixContexts')]
    public function test_access_matrix_character_and_thread_fragment_visibility(bool $isPublic, string $accessSource): void
    {
        $actors = $this->seedActors();
        $campaign = $this->seedCampaign($actors['owner'], $isPublic);
        $scene = $this->seedScene($campaign, $actors['owner']);
        $this->grantCampaignAccessBySource($campaign, $actors, $accessSource);

        $author = User::factory()->create();
        CampaignMembership::query()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $author->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => (int) $actors['owner']->id,
            'assigned_at' => now(),
        ]);

        $character = Character::factory()->create([
            'user_id' => (int) $author->id,
            'world_id' => (int) $campaign->world_id,
        ]);
        $post = Post::factory()->create([
            'scene_id' => (int) $scene->id,
            'user_id' => (int) $author->id,
            'character_id' => (int) $character->id,
            'post_type' => 'ic',
            'content_format' => 'plain',
            'content' => 'Access-Matrix Character Post',
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => (int) $actors['owner']->id,
        ]);

        /** @var BuildPostThreadItemFragmentAction $fragmentAction */
        $fragmentAction = app(BuildPostThreadItemFragmentAction::class);

        foreach (['owner', 'gm', 'trusted_player', 'player', 'outsider', 'admin'] as $role) {
            $user = $actors[$role];

            $characterResponse = $this->actingAs($user)
                ->get(route('characters.show', ['character' => $character]));
            if ($this->expectedCharacterVisible($role, $accessSource)) {
                $characterResponse->assertOk();
            } else {
                $characterResponse->assertForbidden();
            }

            SceneBookmark::query()->updateOrCreate(
                [
                    'user_id' => (int) $user->id,
                    'scene_id' => (int) $scene->id,
                ],
                [
                    'post_id' => (int) $post->id,
                    'label' => 'Matrix Bookmark '.$role,
                ]
            );

            $view = $fragmentAction->execute($post, $user);
            $viewData = $view->getData();
            $bookmarkCountForNav = (int) ($viewData['bookmarkCountForNav'] ?? -1);
            /** @var list<int> $viewableCharacterIds */
            $viewableCharacterIds = $viewData['viewableCharacterIds'] ?? [];

            $this->assertSame(
                $this->expectedCampaignVisible($role, $isPublic, $accessSource) ? 1 : 0,
                $bookmarkCountForNav,
                "bookmarkCountForNav mismatch for role={$role}, source={$accessSource}, public=".($isPublic ? '1' : '0')
            );
            $this->assertSame(
                $this->expectedCharacterVisible($role, $accessSource),
                in_array((int) $character->id, $viewableCharacterIds, true),
                "viewableCharacterIds mismatch for role={$role}, source={$accessSource}, public=".($isPublic ? '1' : '0')
            );
        }
    }

    #[DataProvider('matrixContexts')]
    public function test_access_matrix_notification_recipient_selection(bool $isPublic, string $accessSource): void
    {
        $actors = $this->seedActors();
        $campaign = $this->seedCampaign($actors['owner'], $isPublic);
        $scene = $this->seedScene($campaign, $actors['owner']);
        $this->grantCampaignAccessBySource($campaign, $actors, $accessSource);

        $author = User::factory()->create();
        $post = Post::factory()->create([
            'scene_id' => (int) $scene->id,
            'user_id' => (int) $author->id,
            'post_type' => 'ooc',
            'content_format' => 'plain',
            'content' => 'Access-Matrix Notification Post',
            'moderation_status' => 'approved',
            'approved_at' => now(),
            'approved_by' => (int) $actors['owner']->id,
        ]);

        foreach (['owner', 'gm', 'trusted_player', 'player', 'outsider', 'admin'] as $role) {
            SceneSubscription::query()->updateOrCreate(
                [
                    'scene_id' => (int) $scene->id,
                    'user_id' => (int) $actors[$role]->id,
                ],
                [
                    'is_muted' => false,
                ]
            );
        }

        Notification::fake();
        /** @var ScenePostNotificationService $scenePostNotificationService */
        $scenePostNotificationService = app(ScenePostNotificationService::class);
        $result = $scenePostNotificationService->notifySceneParticipants($post, $author);

        $expectedSceneRecipients = 0;
        foreach (['owner', 'gm', 'trusted_player', 'player', 'outsider', 'admin'] as $role) {
            $user = $actors[$role];
            $shouldReceive = $this->expectedCampaignVisible($role, $isPublic, $accessSource);

            if ($shouldReceive) {
                Notification::assertSentTo($user, SceneNewPostNotification::class);
                $expectedSceneRecipients++;
            } else {
                Notification::assertNotSentTo($user, SceneNewPostNotification::class);
            }
        }
        $this->assertSame($expectedSceneRecipients, (int) ($result['in_app_recipients'] ?? -1));

        /** @var NotifyCampaignGmContactMessageAction $gmContactNotifyAction */
        $gmContactNotifyAction = app(NotifyCampaignGmContactMessageAction::class);
        $thread = CampaignGmContactThread::factory()->create([
            'campaign_id' => (int) $campaign->id,
            'created_by' => (int) $actors['player']->id,
            'subject' => 'Access Matrix Thread',
        ]);

        Notification::fake();
        $gmContactNotifyAction->execute($thread, $actors['player'], 'player->gm');
        Notification::assertSentTo($actors['owner'], CampaignGmContactMessageNotification::class);
        if ($accessSource === 'membership') {
            Notification::assertSentTo($actors['gm'], CampaignGmContactMessageNotification::class);
        } else {
            Notification::assertNotSentTo($actors['gm'], CampaignGmContactMessageNotification::class);
        }
        Notification::assertNotSentTo($actors['trusted_player'], CampaignGmContactMessageNotification::class);
        Notification::assertNotSentTo($actors['player'], CampaignGmContactMessageNotification::class);
        Notification::assertNotSentTo($actors['outsider'], CampaignGmContactMessageNotification::class);
        Notification::assertNotSentTo($actors['admin'], CampaignGmContactMessageNotification::class);

        Notification::fake();
        $gmContactNotifyAction->execute($thread, $actors['gm'], 'gm->player');
        if ($accessSource === 'membership') {
            Notification::assertSentTo($actors['player'], CampaignGmContactMessageNotification::class);
            Notification::assertNotSentTo($actors['owner'], CampaignGmContactMessageNotification::class);
        } else {
            Notification::assertSentTo($actors['owner'], CampaignGmContactMessageNotification::class);
            Notification::assertNotSentTo($actors['player'], CampaignGmContactMessageNotification::class);
        }
        Notification::assertNotSentTo($actors['trusted_player'], CampaignGmContactMessageNotification::class);
        Notification::assertNotSentTo($actors['outsider'], CampaignGmContactMessageNotification::class);
        Notification::assertNotSentTo($actors['admin'], CampaignGmContactMessageNotification::class);
    }

    /**
     * @return array{
     *     owner: User,
     *     gm: User,
     *     trusted_player: User,
     *     player: User,
     *     outsider: User,
     *     admin: User
     * }
     */
    private function seedActors(): array
    {
        return [
            'owner' => User::factory()->gm()->create(),
            'gm' => User::factory()->create(),
            'trusted_player' => User::factory()->create(),
            'player' => User::factory()->create(),
            'outsider' => User::factory()->create(),
            'admin' => User::factory()->admin()->create(),
        ];
    }

    private function seedCampaign(User $owner, bool $isPublic): Campaign
    {
        return Campaign::factory()->create([
            'owner_id' => (int) $owner->id,
            'status' => 'active',
            'is_public' => $isPublic,
        ]);
    }

    private function seedScene(Campaign $campaign, User $owner): Scene
    {
        return Scene::factory()->create([
            'campaign_id' => (int) $campaign->id,
            'created_by' => (int) $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);
    }

    /**
     * @param  array{
     *     owner: User,
     *     gm: User,
     *     trusted_player: User,
     *     player: User,
     *     outsider: User,
     *     admin: User
     * }  $actors
     */
    private function grantCampaignAccessBySource(Campaign $campaign, array $actors, string $accessSource): void
    {
        if ($accessSource === 'membership') {
            $this->createMembership($campaign, $actors['owner'], $actors['gm'], CampaignMembershipRole::GM);
            $this->createMembership($campaign, $actors['owner'], $actors['trusted_player'], CampaignMembershipRole::TRUSTED_PLAYER);
            $this->createMembership($campaign, $actors['owner'], $actors['player'], CampaignMembershipRole::PLAYER);

            return;
        }

        $this->createLegacyInvitationWithoutMembership($campaign, $actors['owner'], $actors['gm'], CampaignInvitation::ROLE_CO_GM);
        $this->createLegacyInvitationWithoutMembership($campaign, $actors['owner'], $actors['trusted_player'], CampaignInvitation::ROLE_TRUSTED_PLAYER);
        $this->createLegacyInvitationWithoutMembership($campaign, $actors['owner'], $actors['player'], CampaignInvitation::ROLE_PLAYER);
    }

    private function createMembership(Campaign $campaign, User $owner, User $user, CampaignMembershipRole $role): void
    {
        CampaignMembership::query()->updateOrCreate(
            [
                'campaign_id' => (int) $campaign->id,
                'user_id' => (int) $user->id,
            ],
            [
                'role' => $role->value,
                'assigned_by' => (int) $owner->id,
                'assigned_at' => now(),
            ]
        );
    }

    private function createLegacyInvitationWithoutMembership(Campaign $campaign, User $owner, User $user, string $role): void
    {
        CampaignInvitation::withoutEvents(function () use ($campaign, $owner, $user, $role): void {
            CampaignInvitation::query()->updateOrCreate(
                [
                    'campaign_id' => (int) $campaign->id,
                    'user_id' => (int) $user->id,
                ],
                [
                    'invited_by' => (int) $owner->id,
                    'status' => CampaignInvitation::STATUS_ACCEPTED,
                    'role' => $role,
                    'accepted_at' => now(),
                    'responded_at' => now(),
                    'created_at' => now(),
                ]
            );
        });
    }

    private function expectedCampaignVisible(string $role, bool $isPublic, string $accessSource): bool
    {
        if ($role === 'owner') {
            return true;
        }

        if ($isPublic) {
            return true;
        }

        if ($accessSource !== 'membership') {
            return false;
        }

        return in_array($role, ['gm', 'trusted_player', 'player'], true);
    }

    private function expectedCanManage(string $role, string $accessSource): bool
    {
        if ($role === 'owner') {
            return true;
        }

        return $accessSource === 'membership' && $role === 'gm';
    }

    private function expectedIsParticipant(string $role, string $accessSource): bool
    {
        if ($role === 'owner') {
            return true;
        }

        return $accessSource === 'membership'
            && in_array($role, ['gm', 'trusted_player', 'player'], true);
    }

    private function expectedCharacterVisible(string $role, string $accessSource): bool
    {
        if ($role === 'owner' || $role === 'admin') {
            return true;
        }

        return $accessSource === 'membership'
            && in_array($role, ['gm', 'trusted_player', 'player'], true);
    }
}
