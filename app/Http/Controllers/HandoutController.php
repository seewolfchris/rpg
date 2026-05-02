<?php

namespace App\Http\Controllers;

use App\Actions\Handout\DeleteHandoutAction;
use App\Actions\Handout\RevealHandoutAction;
use App\Actions\Handout\StoreHandoutAction;
use App\Actions\Handout\UnrevealHandoutAction;
use App\Actions\Handout\UpdateHandoutAction;
use App\Domain\Campaign\CampaignSceneOptionsProvider;
use App\Domain\Handout\HandoutMediaService;
use App\Http\Controllers\Concerns\EnsuresWorldContext;
use App\Http\Requests\Handout\StoreHandoutRequest;
use App\Http\Requests\Handout\UpdateHandoutRequest;
use App\Models\Campaign;
use App\Models\Handout;
use App\Models\World;
use App\Support\Navigation\SafeReturnUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HandoutController extends Controller
{
    use EnsuresWorldContext;

    public function __construct(
        private readonly StoreHandoutAction $storeHandoutAction,
        private readonly UpdateHandoutAction $updateHandoutAction,
        private readonly DeleteHandoutAction $deleteHandoutAction,
        private readonly RevealHandoutAction $revealHandoutAction,
        private readonly UnrevealHandoutAction $unrevealHandoutAction,
        private readonly CampaignSceneOptionsProvider $campaignSceneOptionsProvider,
        private readonly HandoutMediaService $handoutMediaService,
        private readonly SafeReturnUrl $safeReturnUrl,
    ) {}

    public function index(Request $request, World $world, Campaign $campaign): View
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->authorize('viewAny', [Handout::class, $campaign]);

        $user = $this->authenticatedUser($request);
        $canManage = $campaign->canManageCampaign($user);

        $handouts = Handout::query()
            ->where('campaign_id', (int) $campaign->id)
            ->with(['scene', 'creator', 'media'])
            ->when(
                ! $canManage,
                fn ($query) => $query->whereNotNull('revealed_at')
            )
            ->orderByRaw('sort_order IS NULL')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(24)
            ->withQueryString();

        return view('handouts.index', compact('world', 'campaign', 'handouts', 'canManage'));
    }

    public function create(Request $request, World $world, Campaign $campaign): View
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->authorize('create', [Handout::class, $campaign]);

        $handout = new Handout;
        $sceneOptions = $this->campaignSceneOptionsProvider->forCampaign($campaign);
        $fallback = route('campaigns.handouts.index', ['world' => $world, 'campaign' => $campaign]);
        $backUrl = $this->safeReturnUrl->resolve($request, $fallback);
        $returnTo = $this->safeReturnUrl->carry($request);

        return view('handouts.create', compact('world', 'campaign', 'handout', 'sceneOptions', 'backUrl', 'returnTo'));
    }

    public function store(
        StoreHandoutRequest $request,
        World $world,
        Campaign $campaign,
    ): RedirectResponse {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->authorize('create', [Handout::class, $campaign]);

        $actor = $this->authenticatedUser($request);
        $data = $request->validated();
        $handoutFile = $request->file('handout_file');

        if (! $handoutFile instanceof UploadedFile) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'handout_file' => 'Die Handout-Datei konnte nicht geladen werden.',
                ]);
        }

        try {
            $handout = $this->storeHandoutAction->execute($campaign, $actor, $data, $handoutFile);
        } catch (RuntimeException $exception) {
            report($exception);

            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'handout_file' => $exception->getMessage(),
                ]);
        }

        $parameters = [
            'world' => $world,
            'campaign' => $campaign,
            'handout' => $handout,
        ];
        $returnTo = $this->safeReturnUrl->carry($request);
        if (is_string($returnTo) && $returnTo !== '') {
            $parameters['return_to'] = $returnTo;
        }

        return redirect()
            ->route('campaigns.handouts.show', $parameters)
            ->with('status', 'Handout erstellt.');
    }

    public function show(Request $request, World $world, Campaign $campaign, Handout $handout): View
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensureHandoutBelongsToCampaign($campaign, $handout);
        $this->authorize('view', $handout);

        $handout->loadMissing(['scene', 'creator', 'updater', 'media']);
        $fallback = $this->handoutFallbackUrl($world, $campaign, $handout);
        $backUrl = $this->safeReturnUrl->resolve($request, $fallback);
        $returnTo = $this->safeReturnUrl->carry($request);

        return view('handouts.show', compact('world', 'campaign', 'handout', 'backUrl', 'returnTo'));
    }

    public function edit(Request $request, World $world, Campaign $campaign, Handout $handout): View
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensureHandoutBelongsToCampaign($campaign, $handout);
        $this->authorize('update', $handout);

        $handout->loadMissing(['scene', 'creator', 'updater', 'media']);
        $sceneOptions = $this->campaignSceneOptionsProvider->forCampaign($campaign);
        $fallback = $this->handoutFallbackUrl($world, $campaign, $handout);
        $backUrl = $this->safeReturnUrl->resolve($request, $fallback);
        $returnTo = $this->safeReturnUrl->carry($request);

        return view('handouts.edit', compact('world', 'campaign', 'handout', 'sceneOptions', 'backUrl', 'returnTo'));
    }

    public function update(
        UpdateHandoutRequest $request,
        World $world,
        Campaign $campaign,
        Handout $handout
    ): RedirectResponse {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensureHandoutBelongsToCampaign($campaign, $handout);
        $this->authorize('update', $handout);

        $actor = $this->authenticatedUser($request);
        $data = $request->validated();
        $handoutFile = $request->file('handout_file');
        $uploadedHandoutFile = $handoutFile instanceof UploadedFile ? $handoutFile : null;

        try {
            $this->updateHandoutAction->execute($handout, $actor, $data, $uploadedHandoutFile);
        } catch (RuntimeException $exception) {
            report($exception);

            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'handout_file' => $exception->getMessage(),
                ]);
        }

        $parameters = [
            'world' => $world,
            'campaign' => $campaign,
            'handout' => $handout,
        ];
        $returnTo = $this->safeReturnUrl->carry($request);
        if (is_string($returnTo) && $returnTo !== '') {
            $parameters['return_to'] = $returnTo;
        }

        return redirect()
            ->route('campaigns.handouts.show', $parameters)
            ->with('status', 'Handout aktualisiert.');
    }

    public function destroy(World $world, Campaign $campaign, Handout $handout): RedirectResponse
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensureHandoutBelongsToCampaign($campaign, $handout);
        $this->authorize('delete', $handout);

        $this->deleteHandoutAction->execute($handout);

        return redirect()
            ->route('campaigns.handouts.index', [
                'world' => $world,
                'campaign' => $campaign,
            ])
            ->with('status', 'Handout gelöscht.');
    }

    public function reveal(Request $request, World $world, Campaign $campaign, Handout $handout): RedirectResponse
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensureHandoutBelongsToCampaign($campaign, $handout);
        $this->authorize('reveal', $handout);

        $actor = $this->authenticatedUser($request);
        $this->revealHandoutAction->execute($handout, $actor);

        return redirect()
            ->route('campaigns.handouts.show', [
                'world' => $world,
                'campaign' => $campaign,
                'handout' => $handout,
            ])
            ->with('status', 'Handout freigegeben.');
    }

    public function unreveal(Request $request, World $world, Campaign $campaign, Handout $handout): RedirectResponse
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensureHandoutBelongsToCampaign($campaign, $handout);
        $this->authorize('unreveal', $handout);

        $actor = $this->authenticatedUser($request);
        $this->unrevealHandoutAction->execute($handout, $actor);

        return redirect()
            ->route('campaigns.handouts.show', [
                'world' => $world,
                'campaign' => $campaign,
                'handout' => $handout,
            ])
            ->with('status', 'Handout verborgen.');
    }

    public function file(World $world, Campaign $campaign, Handout $handout): BinaryFileResponse
    {
        $this->ensureCampaignBelongsToWorld($world, $campaign);
        $this->ensureHandoutBelongsToCampaign($campaign, $handout);
        $this->authorize('view', $handout);

        $media = $this->handoutMediaService->resolvePrimaryMediaForDelivery($handout);
        abort_unless($media !== null, 404);

        $path = $media->getPath();
        abort_unless($path !== '' && is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => (string) ($media->mime_type ?: 'application/octet-stream'),
            'Content-Disposition' => 'inline; filename="'.$media->file_name.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function handoutFallbackUrl(World $world, Campaign $campaign, Handout $handout): string
    {
        $scene = $handout->scene;
        if ($scene !== null && (int) $scene->campaign_id === (int) $campaign->id) {
            return route('campaigns.scenes.show', [
                'world' => $world,
                'campaign' => $campaign,
                'scene' => $scene,
            ]);
        }

        return route('campaigns.handouts.index', [
            'world' => $world,
            'campaign' => $campaign,
        ]);
    }

}
