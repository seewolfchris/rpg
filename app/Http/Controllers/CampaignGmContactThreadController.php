<?php

namespace App\Http\Controllers;

use App\Actions\CampaignGmContact\BuildCampaignGmContactPanelDataAction;
use App\Actions\CampaignGmContact\StoreCampaignGmContactMessageAction;
use App\Actions\CampaignGmContact\StoreCampaignGmContactThreadAction;
use App\Actions\CampaignGmContact\UpdateCampaignGmContactThreadStatusAction;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\CampaignGmContact\StoreCampaignGmContactMessageRequest;
use App\Http\Requests\CampaignGmContact\StoreCampaignGmContactThreadRequest;
use App\Http\Requests\CampaignGmContact\UpdateCampaignGmContactThreadStatusRequest;
use App\Models\Campaign;
use App\Models\CampaignGmContactThread;
use App\Models\User;
use App\Models\World;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignGmContactThreadController extends Controller
{
    use EnsuresWorldContext;

    public function __construct(
        private readonly BuildCampaignGmContactPanelDataAction $buildCampaignGmContactPanelDataAction,
        private readonly StoreCampaignGmContactThreadAction $storeCampaignGmContactThreadAction,
        private readonly StoreCampaignGmContactMessageAction $storeCampaignGmContactMessageAction,
        private readonly UpdateCampaignGmContactThreadStatusAction $updateCampaignGmContactThreadStatusAction,
    ) {}

    public function store(
        StoreCampaignGmContactThreadRequest $request,
        World $world,
        Campaign $campaign
    ): View|RedirectResponse {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $user = $this->authenticatedUser($request);
        $this->authorize('create', [CampaignGmContactThread::class, $campaign]);

        /** @var array{subject: string, content: string, character_id?: int|null, scene_id?: int|null} $threadData */
        $threadData = [
            'subject' => (string) $request->validated('subject'),
            'content' => (string) $request->validated('content'),
            'character_id' => $request->filled('character_id')
                ? (int) $request->validated('character_id')
                : null,
            'scene_id' => $request->filled('scene_id')
                ? (int) $request->validated('scene_id')
                : null,
        ];

        $thread = $this->storeCampaignGmContactThreadAction->execute(
            campaign: $campaign,
            author: $user,
            data: $threadData,
        );

        if ($this->isHtmxRequest($request)) {
            return $this->renderPanel($world, $campaign, $user, (int) $thread->id);
        }

        return redirect()
            ->to(route('campaigns.show', [
                'world' => $world,
                'campaign' => $campaign,
                'gm_contact_thread' => $thread->id,
            ]).'#gm-contact-panel')
            ->with('status', 'SL-Kontakt eröffnet.');
    }

    public function show(
        Request $request,
        World $world,
        Campaign $campaign,
        CampaignGmContactThread $gmContactThread
    ): View|RedirectResponse {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensureThreadBelongsToCampaign($campaign, $gmContactThread);
        $this->authorize('view', $gmContactThread);
        $user = $this->authenticatedUser($request);

        if (! $this->isHtmxRequest($request)) {
            return redirect()->to(route('campaigns.show', [
                'world' => $world,
                'campaign' => $campaign,
                'gm_contact_thread' => $gmContactThread->id,
            ]).'#gm-contact-panel');
        }

        return $this->renderThreadDetail($world, $campaign, $user, (int) $gmContactThread->id);
    }

    public function storeMessage(
        StoreCampaignGmContactMessageRequest $request,
        World $world,
        Campaign $campaign,
        CampaignGmContactThread $gmContactThread
    ): View|RedirectResponse {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensureThreadBelongsToCampaign($campaign, $gmContactThread);
        $this->authorize('reply', $gmContactThread);
        $user = $this->authenticatedUser($request);

        /** @var array{content: string} $messageData */
        $messageData = [
            'content' => (string) $request->validated('content'),
        ];

        $this->storeCampaignGmContactMessageAction->execute(
            thread: $gmContactThread,
            author: $user,
            data: $messageData,
        );

        if ($this->isHtmxRequest($request)) {
            return $this->renderPanel($world, $campaign, $user, (int) $gmContactThread->id);
        }

        return redirect()
            ->to(route('campaigns.show', [
                'world' => $world,
                'campaign' => $campaign,
                'gm_contact_thread' => $gmContactThread->id,
            ]).'#gm-contact-panel')
            ->with('status', 'Nachricht gesendet.');
    }

    public function updateStatus(
        UpdateCampaignGmContactThreadStatusRequest $request,
        World $world,
        Campaign $campaign,
        CampaignGmContactThread $gmContactThread
    ): View|RedirectResponse {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensureThreadBelongsToCampaign($campaign, $gmContactThread);
        $this->authorize('updateStatus', $gmContactThread);
        $user = $this->authenticatedUser($request);

        $thread = $this->updateCampaignGmContactThreadStatusAction->execute(
            thread: $gmContactThread,
            status: (string) $request->validated('status'),
        );

        if ($this->isHtmxRequest($request)) {
            return $this->renderPanel($world, $campaign, $user, (int) $thread->id);
        }

        return redirect()
            ->to(route('campaigns.show', [
                'world' => $world,
                'campaign' => $campaign,
                'gm_contact_thread' => $thread->id,
            ]).'#gm-contact-panel')
            ->with('status', 'Thread-Status aktualisiert.');
    }

    private function ensureThreadBelongsToCampaign(Campaign $campaign, CampaignGmContactThread $thread): void
    {
        abort_unless((int) $thread->campaign_id === (int) $campaign->id, 404);
    }

    private function renderPanel(World $world, Campaign $campaign, User $user, ?int $selectedThreadId = null): View
    {
        $gmContactPanelData = $this->buildCampaignGmContactPanelDataAction->execute(
            campaign: $campaign,
            user: $user,
            selectedThreadId: $selectedThreadId,
            canManageCampaign: $this->canManageCampaign($campaign, $user),
        );

        return view('campaigns.partials.gm-contacts-panel', compact('world', 'campaign', 'gmContactPanelData'));
    }

    private function renderThreadDetail(World $world, Campaign $campaign, User $user, int $selectedThreadId): View
    {
        $gmContactPanelData = $this->buildCampaignGmContactPanelDataAction->execute(
            campaign: $campaign,
            user: $user,
            selectedThreadId: $selectedThreadId,
            canManageCampaign: $this->canManageCampaign($campaign, $user),
        );
        $selectedThread = $gmContactPanelData->selectedThread;

        abort_unless($selectedThread instanceof CampaignGmContactThread, 403);

        return view('campaigns.partials.gm-contact-thread-detail', [
            'world' => $world,
            'campaign' => $campaign,
            'selectedThread' => $selectedThread,
            'selectedThreadMessages' => $gmContactPanelData->selectedThreadMessages,
            'isGmSide' => $gmContactPanelData->isGmSide,
        ]);
    }

    private function isHtmxRequest(Request $request): bool
    {
        return $request->header('HX-Request') === 'true';
    }

    private function canManageCampaign(Campaign $campaign, User $user): bool
    {
        return (int) $campaign->owner_id === (int) $user->id
            || $user->isGmOrAdmin()
            || $campaign->isCoGm($user);
    }
}
