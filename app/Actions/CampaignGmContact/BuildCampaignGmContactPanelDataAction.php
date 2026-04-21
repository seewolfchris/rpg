<?php

namespace App\Actions\CampaignGmContact;

use App\Models\Campaign;
use App\Models\CampaignGmContactThread;
use App\Models\Character;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Support\Collection;

class BuildCampaignGmContactPanelDataAction
{
    public function execute(
        Campaign $campaign,
        User $user,
        ?int $selectedThreadId = null,
        string $sceneStatus = 'all',
        string $sceneSearch = '',
        bool $canManageCampaign = false,
    ): CampaignGmContactPanelData {
        $canCreateThread = CampaignGmContactThread::hasCampaignContactAccess($campaign, $user);
        $isGmSide = CampaignGmContactThread::isGmSide($campaign, $user);

        $threads = collect();
        $selectedThread = null;
        $selectedThreadMessages = collect();

        if ($canCreateThread) {
            $threadsQuery = CampaignGmContactThread::query()
                ->visibleTo($user, $campaign)
                ->with(CampaignGmContactThread::PANEL_RELATIONS)
                ->orderedByActivity();

            $threads = $threadsQuery->get();

            $selectedThread = $this->resolveSelectedThread($threads, $selectedThreadId);

            if ($selectedThread instanceof CampaignGmContactThread) {
                $selectedThreadMessages = $selectedThread->messages()
                    ->with('user')
                    ->orderBy('created_at')
                    ->get();
            }
        }

        $sceneOptions = $this->sceneOptions(
            campaign: $campaign,
            canCreateThread: $canCreateThread,
            sceneStatus: $sceneStatus,
            sceneSearch: $sceneSearch,
            canManageCampaign: $canManageCampaign,
        );
        $characterOptions = $this->characterOptions($campaign, $user, $canCreateThread);

        return new CampaignGmContactPanelData(
            threads: $threads,
            selectedThread: $selectedThread,
            selectedThreadMessages: $selectedThreadMessages,
            sceneOptions: $sceneOptions,
            characterOptions: $characterOptions,
            canCreateThread: $canCreateThread,
            isGmSide: $isGmSide,
        );
    }

    /**
     * @param  Collection<int, CampaignGmContactThread>  $threads
     */
    private function resolveSelectedThread(Collection $threads, ?int $selectedThreadId): ?CampaignGmContactThread
    {
        if ($threads->isEmpty()) {
            return null;
        }

        if ($selectedThreadId !== null && $selectedThreadId > 0) {
            /** @var CampaignGmContactThread|null $selected */
            $selected = $threads->firstWhere('id', $selectedThreadId);

            if ($selected instanceof CampaignGmContactThread) {
                return $selected;
            }
        }

        /** @var CampaignGmContactThread|null $first */
        $first = $threads->first();

        return $first;
    }

    /**
     * @return Collection<int, Scene>
     */
    private function sceneOptions(
        Campaign $campaign,
        bool $canCreateThread,
        string $sceneStatus,
        string $sceneSearch,
        bool $canManageCampaign,
    ): Collection {
        if (! $canCreateThread) {
            return collect();
        }

        $status = in_array($sceneStatus, ['all', 'open', 'closed', 'archived'], true)
            ? $sceneStatus
            : 'all';
        $search = trim($sceneSearch);

        $query = $campaign->scenes()
            ->when(
                ! $canManageCampaign,
                fn ($builder) => $builder->where('status', '!=', 'archived')
            );

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $searchTerm = '%'.$search.'%';
            $query->where(function ($builder) use ($searchTerm): void {
                $builder
                    ->where('title', 'like', $searchTerm)
                    ->orWhere('summary', 'like', $searchTerm);
            });
        }

        return $query
            ->orderBy('position')
            ->orderBy('created_at')
            ->get(['id', 'title', 'status', 'position']);
    }

    /**
     * @return Collection<int, Character>
     */
    private function characterOptions(Campaign $campaign, User $user, bool $canCreateThread): Collection
    {
        if (! $canCreateThread) {
            return collect();
        }

        return $user->characters()
            ->where('world_id', (int) $campaign->world_id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
