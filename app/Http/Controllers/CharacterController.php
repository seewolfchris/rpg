<?php

namespace App\Http\Controllers;

use App\Actions\Character\BuildCharacterCreateDataAction;
use App\Actions\Character\BuildCharacterEditDataAction;
use App\Actions\Character\BuildCharacterIndexDataAction;
use App\Actions\Character\BuildCharacterShowDataAction;
use App\Actions\Character\CreateCharacterAction;
use App\Actions\Character\DeleteCharacterAction;
use App\Actions\Character\UpdateCharacterAction;
use App\Actions\Character\UpdateCharacterInlineAction;
use App\Data\Character\CreateCharacterInput;
use App\Data\Character\InlineUpdateCharacterInput;
use App\Data\Character\UpdateCharacterInput;
use App\Exceptions\CharacterDeletionFailedException;
use App\Http\Requests\Character\InlineUpdateCharacterRequest;
use App\Http\Requests\Character\StoreCharacterRequest;
use App\Http\Requests\Character\UpdateCharacterRequest;
use App\Models\Character;
use App\Models\World;
use App\Support\Navigation\SafeReturnUrl;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use Throwable;

class CharacterController extends Controller
{
    public function __construct(
        private readonly BuildCharacterCreateDataAction $buildCharacterCreateDataAction,
        private readonly BuildCharacterEditDataAction $buildCharacterEditDataAction,
        private readonly BuildCharacterIndexDataAction $buildCharacterIndexDataAction,
        private readonly BuildCharacterShowDataAction $buildCharacterShowDataAction,
        private readonly CreateCharacterAction $createCharacterAction,
        private readonly DeleteCharacterAction $deleteCharacterAction,
        private readonly UpdateCharacterAction $updateCharacterAction,
        private readonly UpdateCharacterInlineAction $updateCharacterInlineAction,
        private readonly SafeReturnUrl $safeReturnUrl,
    ) {}

    public function index(Request $request): View
    {
        $user = $this->authenticatedUser($request);
        $selectedWorldSlug = trim((string) $request->query('world', (string) $request->session()->get('world_slug', World::defaultSlug())));
        $selectedStatus = (string) $request->query('status', 'all');
        $indexData = $this->buildCharacterIndexDataAction->execute(
            user: $user,
            selectedWorldSlug: $selectedWorldSlug,
            selectedStatus: $selectedStatus,
        );

        if ($indexData->selectedWorld instanceof World) {
            $request->session()->put('world_slug', $indexData->selectedWorld->slug);
        }

        return view('characters.index', [
            'characters' => $indexData->characters,
            'worlds' => $indexData->worlds,
            'selectedWorld' => $indexData->selectedWorld,
            'selectedStatus' => $indexData->selectedStatus,
            'characterStatuses' => $indexData->characterStatuses,
        ]);
    }

    public function create(Request $request): View
    {
        $selectedWorldSlug = trim((string) $request->query('world', (string) $request->session()->get('world_slug', World::defaultSlug())));
        $createData = $this->buildCharacterCreateDataAction->execute($selectedWorldSlug);
        $fallback = route('characters.index');
        $backUrl = $this->safeReturnUrl->resolve($request, $fallback);
        $returnTo = $this->safeReturnUrl->carry($request);

        return view('characters.create', [
            'worlds' => $createData->worlds,
            'selectedWorld' => $createData->selectedWorld,
            'backUrl' => $backUrl,
            'returnTo' => $returnTo,
        ]);
    }

    public function store(StoreCharacterRequest $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        $avatar = $request->file('avatar');

        $character = $this->createCharacterAction->execute(
            new CreateCharacterInput(
                actor: $user,
                payload: $request->validated(),
                avatar: $avatar instanceof UploadedFile ? $avatar : null,
            ),
        );

        $parameters = ['character' => $character];
        $returnTo = $this->safeReturnUrl->carry($request);
        if (is_string($returnTo) && $returnTo !== '') {
            $parameters['return_to'] = $returnTo;
        }

        return redirect()
            ->route('characters.show', $parameters)
            ->with('status', 'Charakter erstellt.');
    }

    public function show(Request $request, Character $character): View|RedirectResponse
    {
        $this->authorize('view', $character);

        try {
            $showData = $this->buildCharacterShowDataAction->execute($character);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('characters.index')
                ->with('error', 'Charakterdetails konnten nicht geladen werden.');
        }

        return view('characters.show', [
            'character' => $showData->character,
            'inventoryLogs' => $showData->inventoryLogs,
            'progressionEvents' => $showData->progressionEvents,
            'progressionState' => $showData->progressionState,
            'backUrl' => $this->safeReturnUrl->resolve($request, route('characters.index')),
            'returnTo' => $this->safeReturnUrl->carry($request),
        ]);
    }

    public function edit(Request $request, Character $character): View
    {
        $this->authorize('update', $character);
        $editData = $this->buildCharacterEditDataAction->execute();
        $backUrl = $this->safeReturnUrl->resolve($request, route('characters.index'));
        $returnTo = $this->safeReturnUrl->carry($request);

        return view('characters.edit', [
            'character' => $character,
            'worlds' => $editData->worlds,
            'backUrl' => $backUrl,
            'returnTo' => $returnTo,
        ]);
    }

    public function update(UpdateCharacterRequest $request, Character $character): RedirectResponse
    {
        $this->authorize('update', $character);
        $user = $this->authenticatedUser($request);
        $avatar = $request->file('avatar');

        $this->updateCharacterAction->execute(
            new UpdateCharacterInput(
                actor: $user,
                character: $character,
                payload: $request->validated(),
                removeAvatar: $request->boolean('remove_avatar'),
                avatar: $avatar instanceof UploadedFile ? $avatar : null,
            ),
        );

        $parameters = ['character' => $character];
        $returnTo = $this->safeReturnUrl->carry($request);
        if (is_string($returnTo) && $returnTo !== '') {
            $parameters['return_to'] = $returnTo;
        }

        return redirect()
            ->route('characters.show', $parameters)
            ->with('status', 'Charakter aktualisiert.');
    }

    public function inlineUpdate(InlineUpdateCharacterRequest $request, Character $character): View|RedirectResponse
    {
        $this->authorize('update', $character);
        $validated = $request->validated();
        $result = $this->updateCharacterInlineAction->execute(
            new InlineUpdateCharacterInput(
                character: $character,
                payload: $validated,
                isHtmxRequest: $request->header('HX-Request') === 'true',
            ),
        );

        if ($result->shouldRenderFragment) {
            return view('characters.partials.inline-editor', ['character' => $result->character]);
        }

        return redirect()
            ->route('characters.show', $result->character)
            ->with('status', 'Charakter-Schnellbearbeitung gespeichert.');
    }

    public function destroy(Character $character): RedirectResponse
    {
        $this->authorize('delete', $character);

        try {
            $this->deleteCharacterAction->execute($character);
        } catch (ModelNotFoundException) {
            return redirect()
                ->route('characters.index')
                ->with('status', 'Charakter war bereits gelöscht.');
        } catch (CharacterDeletionFailedException $exception) {
            report($exception);

            return redirect()
                ->route('characters.index')
                ->with('error', 'Charakter konnte nicht gelöscht werden.');
        }

        return redirect()
            ->route('characters.index')
            ->with('status', 'Charakter gelöscht.');
    }
}
