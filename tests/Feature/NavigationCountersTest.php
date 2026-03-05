<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Scene;
use App\Models\SceneBookmark;
use App\Models\User;
use App\Support\NavigationCounters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NavigationCountersTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_counters_return_zero_for_guest_context(): void
    {
        $counts = app(NavigationCounters::class)->forUser(null);

        $this->assertSame([
            'unreadNotificationsCount' => 0,
            'pendingCampaignInvitationsCount' => 0,
            'bookmarkCount' => 0,
        ], $counts);
    }

    public function test_navigation_counters_include_only_pending_invitations_and_visible_bookmarks(): void
    {
        $target = User::factory()->create();
        $owner = User::factory()->gm()->create();

        $publicCampaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => true,
            'status' => 'active',
        ]);
        $privateHiddenCampaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
        ]);
        $privateAcceptedCampaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
        ]);
        $privatePendingCampaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'is_public' => false,
            'status' => 'active',
        ]);

        $privateAcceptedCampaign->invitations()->create([
            'user_id' => $target->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);

        $privatePendingCampaign->invitations()->create([
            'user_id' => $target->id,
            'invited_by' => $owner->id,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'created_at' => now(),
        ]);

        $publicScene = Scene::factory()->create([
            'campaign_id' => $publicCampaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
        ]);
        $hiddenScene = Scene::factory()->create([
            'campaign_id' => $privateHiddenCampaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
        ]);
        $acceptedScene = Scene::factory()->create([
            'campaign_id' => $privateAcceptedCampaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
        ]);

        SceneBookmark::query()->create([
            'user_id' => $target->id,
            'scene_id' => $publicScene->id,
            'label' => 'Sichtbar oeffentlich',
        ]);
        SceneBookmark::query()->create([
            'user_id' => $target->id,
            'scene_id' => $hiddenScene->id,
            'label' => 'Versteckt privat',
        ]);
        SceneBookmark::query()->create([
            'user_id' => $target->id,
            'scene_id' => $acceptedScene->id,
            'label' => 'Sichtbar per Einladung',
        ]);

        $readNotification = $this->createDatabaseNotification($target);
        $readNotification->markAsRead();
        $this->createDatabaseNotification($target);

        $counts = app(NavigationCounters::class)->forUser($target);

        $this->assertSame(1, $counts['unreadNotificationsCount']);
        $this->assertSame(1, $counts['pendingCampaignInvitationsCount']);
        $this->assertSame(2, $counts['bookmarkCount']);
    }

    private function createDatabaseNotification(User $user): DatabaseNotification
    {
        $user->notify(new class extends Notification
        {
            public function via($notifiable): array
            {
                return ['database'];
            }

            public function toArray($notifiable): array
            {
                return [
                    'id' => (string) Str::uuid(),
                    'type' => 'counter-test',
                ];
            }
        });

        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->latest('created_at')->firstOrFail();

        return $notification;
    }
}
