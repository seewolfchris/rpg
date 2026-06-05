<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Domain\Post\PostModerationScope;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Character;
use App\Models\Post;
use App\Models\SceneSubscription;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\Eloquent\Builder;

final class BuildDashboardNextStepAction
{
    public function __construct(
        private readonly PostModerationScope $postModerationScope,
    ) {}

    public function execute(User $user, ?World $selectedWorld): DashboardNextStepData
    {
        if (! $selectedWorld instanceof World) {
            return new DashboardNextStepData(
                eyebrow: 'Nächste Aktion',
                title: 'Welt wählen',
                description: 'Wähle zuerst eine aktive Welt, damit Kampagnen, Wissen und Charaktere im richtigen Kontext öffnen.',
                primaryLabel: 'Welt wählen',
                primaryUrl: route('worlds.index'),
                secondaryLabel: 'Erste Schritte ins Spiel',
                secondaryUrl: route('knowledge.global.getting-started'),
            );
        }

        $pendingInvitation = $this->pendingInvitationInWorld($user, $selectedWorld);
        if ($pendingInvitation instanceof CampaignInvitation) {
            return new DashboardNextStepData(
                eyebrow: 'Offene Einladung',
                title: 'Einladung ansehen',
                description: 'Du wurdest zu einer Kampagne in der aktiven Welt eingeladen. Nimm sie an oder lehne sie ab, bevor du weiterspielst.',
                primaryLabel: 'Einladung ansehen',
                primaryUrl: route('campaign-invitations.index'),
                secondaryLabel: 'Erste Schritte ins Spiel',
                secondaryUrl: route('knowledge.getting-started', ['world' => $selectedWorld]),
                meta: $pendingInvitation->campaign instanceof Campaign
                    ? 'Kampagne: '.$pendingInvitation->campaign->title
                    : null,
            );
        }

        $visibleCampaign = $this->visibleCampaignInWorld($user, $selectedWorld);
        if (! $visibleCampaign instanceof Campaign) {
            return new DashboardNextStepData(
                eyebrow: 'Kampagne',
                title: 'Kampagnen öffnen',
                description: 'In der aktiven Welt ist für dich noch keine Kampagne sichtbar. Prüfe die Kampagnenliste oder warte auf eine Einladung.',
                primaryLabel: 'Kampagnen öffnen',
                primaryUrl: route('campaigns.index', ['world' => $selectedWorld]),
                secondaryLabel: 'Erste Schritte ins Spiel',
                secondaryUrl: route('knowledge.getting-started', ['world' => $selectedWorld]),
                meta: 'Aktive Welt: '.$selectedWorld->name,
            );
        }

        if (! $this->hasCharacterInWorld($user, $selectedWorld)) {
            return new DashboardNextStepData(
                eyebrow: 'Charakter',
                title: 'Charakter erstellen',
                description: 'Lege zuerst eine Figur für diese Welt an. Danach kannst du sie in passenden Szenen verwenden.',
                primaryLabel: 'Charakter erstellen',
                primaryUrl: route('characters.create', ['world' => $selectedWorld->slug]),
                secondaryLabel: 'Kampagne öffnen',
                secondaryUrl: route('campaigns.show', ['world' => $selectedWorld, 'campaign' => $visibleCampaign]),
                meta: 'Aktive Welt: '.$selectedWorld->name,
            );
        }

        $pendingModerationCount = $this->pendingModerationCount($user, $selectedWorld);
        if ($pendingModerationCount > 0) {
            return new DashboardNextStepData(
                eyebrow: 'Spielleitung',
                title: 'Moderation öffnen',
                description: 'In der aktiven Welt warten Beiträge auf Prüfung. Moderation kann den Spielfluss blockieren und steht deshalb vor Lesestatus.',
                primaryLabel: 'Moderation öffnen',
                primaryUrl: route('gm.moderation.index', ['world' => $selectedWorld, 'status' => 'pending']),
                secondaryLabel: 'Kampagne öffnen',
                secondaryUrl: route('campaigns.show', ['world' => $selectedWorld, 'campaign' => $visibleCampaign]),
                meta: 'Ausstehend: '.$pendingModerationCount,
            );
        }

        $unreadSceneCount = $this->unreadSceneCount($user, $selectedWorld);
        if ($unreadSceneCount > 0) {
            return new DashboardNextStepData(
                eyebrow: 'Szenen',
                title: 'Ungelesene Szenen öffnen',
                description: 'Es gibt neue Beiträge in deinen abonnierten sichtbaren Szenen dieser Welt.',
                primaryLabel: 'Ungelesene Szenen öffnen',
                primaryUrl: route('scene-subscriptions.index', ['world' => $selectedWorld]),
                secondaryLabel: 'Kampagne öffnen',
                secondaryUrl: route('campaigns.show', ['world' => $selectedWorld, 'campaign' => $visibleCampaign]),
                meta: 'Ungelesene Szenen: '.$unreadSceneCount,
            );
        }

        return new DashboardNextStepData(
            eyebrow: 'Kampagne',
            title: 'Kampagne öffnen',
            description: 'Du hast eine sichtbare Kampagne und einen Charakter in der aktiven Welt. Öffne die Kampagne und lies die nächste Szene.',
            primaryLabel: 'Kampagne öffnen',
            primaryUrl: route('campaigns.show', ['world' => $selectedWorld, 'campaign' => $visibleCampaign]),
            secondaryLabel: 'Erste Schritte ins Spiel',
            secondaryUrl: route('knowledge.getting-started', ['world' => $selectedWorld]),
            meta: 'Aktive Welt: '.$selectedWorld->name,
        );
    }

    private function pendingInvitationInWorld(User $user, World $world): ?CampaignInvitation
    {
        return CampaignInvitation::query()
            ->with('campaign')
            ->where('user_id', (int) $user->id)
            ->where('status', CampaignInvitation::STATUS_PENDING)
            ->whereHas('campaign', function (Builder $campaignQuery) use ($world): void {
                $campaignQuery->where('world_id', (int) $world->id);
            })
            ->latest('created_at')
            ->first();
    }

    private function visibleCampaignInWorld(User $user, World $world): ?Campaign
    {
        return Campaign::query()
            ->forWorld($world)
            ->visibleTo($user)
            ->latest()
            ->first();
    }

    private function hasCharacterInWorld(User $user, World $world): bool
    {
        return Character::query()
            ->where('user_id', (int) $user->id)
            ->where('world_id', (int) $world->id)
            ->exists();
    }

    private function pendingModerationCount(User $user, World $world): int
    {
        if (! $this->postModerationScope->canAccessWorldQueue($user, $world)) {
            return 0;
        }

        return (clone $this->postModerationScope
            ->baseQuery($user, $world))
            ->where('moderation_status', 'pending')
            ->count();
    }

    private function unreadSceneCount(User $user, World $world): int
    {
        $latestPostsPerScene = Post::query()
            ->selectRaw('scene_id, MAX(id) as latest_post_id')
            ->groupBy('scene_id');

        return (int) SceneSubscription::query()
            ->where('scene_subscriptions.user_id', (int) $user->id)
            ->whereHas('scene.campaign', function (Builder $campaignQuery) use ($user, $world): void {
                $campaignQuery
                    ->where('world_id', (int) $world->id)
                    ->whereIn('id', Campaign::query()->visibleTo($user)->select('id'));
            })
            ->leftJoinSub($latestPostsPerScene, 'latest_posts', function ($join): void {
                $join->on('scene_subscriptions.scene_id', '=', 'latest_posts.scene_id');
            })
            ->whereNotNull('latest_posts.latest_post_id')
            ->where(function ($query): void {
                $query->whereNull('scene_subscriptions.last_read_post_id')
                    ->orWhereColumn('scene_subscriptions.last_read_post_id', '<', 'latest_posts.latest_post_id');
            })
            ->count();
    }
}
